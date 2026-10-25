<?php

namespace App\Http\Controllers;

use App\Models\LaundryOrder;
use App\Models\LaundryOrderEvent;
use App\Models\LaundryService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LaundryController extends Controller
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['picked_up', 'cancelled'],
        'picked_up' => ['processing'], 'processing' => ['ready'], 'ready' => ['delivered'],
        'delivered' => [], 'cancelled' => [],
    ];

    private function privileged(User $user): bool { return $user->is_admin || $user->is_staff; }

    public function index(Request $request)
    {
        $services = LaundryService::where('active', true)->orderBy('name')->get();
        $orders = LaundryOrder::with('items')->where('customer_id', $request->user()->id)->latest()->paginate(15);
        return view('laundry.index', compact('services', 'orders'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pickup_at' => 'required|date|after:now', 'delivery_at' => 'required|date|after:pickup_at',
            'pickup_address' => 'required|string|min:8|max:500', 'customer_note' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:cash_on_delivery,manual',
            'items' => 'required|array|min:1|max:20', 'items.*.service_id' => 'required|integer|distinct|exists:laundry_services,id',
            'items.*.quantity' => 'required|string|max:12',
        ]);
        $services = LaundryService::whereIn('id', collect($data['items'])->pluck('service_id'))->where('active', true)->get()->keyBy('id');
        if ($services->count() !== count($data['items'])) throw ValidationException::withMessages(['items' => 'A selected service is unavailable. Refresh and try again.']);

        $lineItems = []; $total = 0;
        foreach ($data['items'] as $input) {
            $service = $services[$input['service_id']]; $raw = $input['quantity'];
            if ($service->unit === 'kg') {
                if (!preg_match('/^(0|[1-9][0-9]{0,2})(?:\.([0-9]{1,3}))?$/', $raw)) throw ValidationException::withMessages(['items' => 'Weight must be a positive value up to 100.000 kg.']);
                [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, ''); $quantity = (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
                if ($quantity < 100 || $quantity > 100000) throw ValidationException::withMessages(['items' => 'Weight must be between 0.100 and 100 kg.']);
                $lineTotal = intdiv($service->price_minor * $quantity + 500, 1000);
            } else {
                if (!preg_match('/^[1-9][0-9]{0,2}$/', $raw) || (int) $raw > 500) throw ValidationException::withMessages(['items' => 'Item quantity must be an integer between 1 and 500.']);
                $quantity = (int) $raw; $lineTotal = $service->price_minor * $quantity;
            }
            if ($lineTotal < 1 || $total > 100000000000 - $lineTotal) throw ValidationException::withMessages(['items' => 'Order total exceeds the allowed maximum.']);
            $total += $lineTotal;
            $lineItems[] = ['laundry_service_id' => $service->id, 'service_name' => $service->name, 'unit' => $service->unit, 'quantity_units' => $quantity, 'unit_price_minor' => $service->price_minor, 'line_total_minor' => $lineTotal];
        }
        $order = DB::transaction(function () use ($request, $data, $lineItems, $total) {
            $order = LaundryOrder::create(['reference' => 'LDY-'.Str::upper(Str::random(12)), 'customer_id' => $request->user()->id, 'pickup_at' => $data['pickup_at'], 'delivery_at' => $data['delivery_at'], 'pickup_address' => $data['pickup_address'], 'customer_note' => $data['customer_note'] ?? null, 'status' => 'pending', 'payment_method' => $data['payment_method'], 'payment_status' => 'unpaid', 'total_minor' => $total]);
            $order->items()->createMany($lineItems);
            $this->event($order, $request->user()->id, 'status', null, 'pending', 'Order created');
            $this->event($order, $request->user()->id, 'payment', null, 'unpaid', 'Payment method: '.$data['payment_method']);
            return $order;
        });
        return redirect()->route('laundry.orders.show', $order)->with('status', 'Order created. Receipt reference: '.$order->reference);
    }

    private function event(LaundryOrder $order, ?int $actor, string $type, ?string $from, string $to, ?string $note = null): void
    {
        LaundryOrderEvent::create(['laundry_order_id' => $order->id, 'actor_user_id' => $actor, 'event_type' => $type, 'from_value' => $from, 'to_value' => $to, 'note' => $note]);
    }

    private function canView(User $user, LaundryOrder $order): bool
    {
        return $user->is_admin || $order->customer_id === $user->id || ($user->is_staff && $order->assigned_staff_id === $user->id);
    }

    public function show(Request $request, LaundryOrder $order)
    {
        abort_unless($this->canView($request->user(), $order), 403);
        $order->load(['items', 'customer', 'staff', 'events.actor']);
        return view('laundry.show', compact('order'));
    }

    public function cancel(Request $request, LaundryOrder $order)
    {
        abort_unless($order->customer_id === $request->user()->id, 403);
        if (!in_array($order->status, ['pending', 'confirmed'], true)) throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
        DB::transaction(function () use ($request, $order) {
            $locked = LaundryOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, ['pending', 'confirmed'], true)) throw ValidationException::withMessages(['status' => 'This order can no longer be cancelled.']);
            $from = $locked->status; $locked->update(['status' => 'cancelled']); $this->event($locked, $request->user()->id, 'status', $from, 'cancelled', 'Cancelled by customer');
        });
        return back()->with('status', 'Order cancelled. Payment status remains unchanged. Contact staff about any offline payment/refund.');
    }

    public function staff(Request $request)
    {
        abort_unless($this->privileged($request->user()), 403);
        $query = LaundryOrder::with(['customer', 'staff', 'items'])->latest();
        if (!$request->user()->is_admin) $query->where('assigned_staff_id', $request->user()->id);
        $orders = $query->paginate(30); $staffMembers = User::where('is_staff', true)->orWhere('is_admin', true)->orderBy('name')->get();
        return view('laundry.staff', compact('orders', 'staffMembers'));
    }

    public function update(Request $request, LaundryOrder $order)
    {
        $user = $request->user(); abort_unless($this->privileged($user), 403);
        if (!$user->is_admin && $order->assigned_staff_id !== $user->id) abort(403);
        $data = $request->validate(['status' => 'nullable|in:pending,confirmed,picked_up,processing,ready,delivered,cancelled', 'payment_status' => 'nullable|in:unpaid,paid,refunded', 'assigned_staff_id' => 'nullable|integer|exists:users,id', 'note' => 'nullable|string|max:500']);
        if (isset($data['assigned_staff_id']) && !User::whereKey($data['assigned_staff_id'])->where(fn ($q) => $q->where('is_staff', true)->orWhere('is_admin', true))->exists()) throw ValidationException::withMessages(['assigned_staff_id' => 'Choose an active staff or administrator account.']);
        DB::transaction(function () use ($order, $user, $data) {
            $locked = LaundryOrder::whereKey($order->id)->lockForUpdate()->firstOrFail(); $changes = [];
            if (isset($data['status']) && $data['status'] !== $locked->status) {
                if (!in_array($data['status'], self::TRANSITIONS[$locked->status] ?? [], true)) throw ValidationException::withMessages(['status' => 'Invalid order status transition.']);
                $this->event($locked, $user->id, 'status', $locked->status, $data['status'], $data['note'] ?? null); $changes['status'] = $data['status'];
            }
            if (isset($data['payment_status']) && $data['payment_status'] !== $locked->payment_status) {
                if (!$user->is_admin && $data['payment_status'] !== 'paid') abort(403);
                if ($locked->payment_status === 'refunded' || ($data['payment_status'] === 'refunded' && $locked->payment_status !== 'paid')) throw ValidationException::withMessages(['payment_status' => 'Invalid payment status transition.']);
                $this->event($locked, $user->id, 'payment', $locked->payment_status, $data['payment_status'], $data['note'] ?? null); $changes['payment_status'] = $data['payment_status'];
            }
            if (array_key_exists('assigned_staff_id', $data) && $user->is_admin && $data['assigned_staff_id'] != $locked->assigned_staff_id) {
                $this->event($locked, $user->id, 'assignment', $locked->assigned_staff_id === null ? null : (string) $locked->assigned_staff_id, $data['assigned_staff_id'] === null ? 'unassigned' : (string) $data['assigned_staff_id'], $data['note'] ?? null);
                $changes['assigned_staff_id'] = $data['assigned_staff_id'];
            }
            if ($changes) $locked->update($changes);
        });
        return back()->with('status', 'Order updated.');
    }

    public function services(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $services = LaundryService::orderBy('name')->get();
        return view('laundry.services', compact('services'));
    }

    public function saveService(Request $request, ?LaundryService $service = null)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:1000', 'unit' => 'required|in:kg,item', 'price' => ['required','string','regex:/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/'], 'active' => 'required|boolean']);
        [$whole, $fraction] = array_pad(explode('.', $data['price'], 2), 2, ''); $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($minor < 1) throw ValidationException::withMessages(['price' => 'Price must be greater than zero.']);
        $service ??= new LaundryService();
        $service->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'unit' => $data['unit'], 'price_minor' => $minor, 'active' => $data['active']])->save();
        return back()->with('status', 'Service saved. Existing order items retain their price snapshot.');
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        $daily = collect(range(0, 30))->map(function (int $offset) {
            $date = today()->subDays($offset); $query = LaundryOrder::whereDate('created_at', $date); $active = (clone $query)->where('status', '!=', 'cancelled');
            return (object) ['period' => $date->toDateString(), 'orders' => (clone $query)->count(), 'ordered_minor' => (clone $active)->sum('total_minor'), 'paid_minor' => (clone $active)->where('payment_status', 'paid')->sum('total_minor')];
        })->filter(fn ($row) => $row->orders > 0)->values();
        $monthly = collect(range(0, 11))->map(function (int $offset) {
            $date = now()->startOfMonth()->subMonths($offset); $query = LaundryOrder::whereBetween('created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]); $active = (clone $query)->where('status', '!=', 'cancelled');
            return (object) ['period' => $date->format('Y-m'), 'orders' => (clone $query)->count(), 'ordered_minor' => (clone $active)->sum('total_minor'), 'paid_minor' => (clone $active)->where('payment_status', 'paid')->sum('total_minor')];
        })->filter(fn ($row) => $row->orders > 0)->values();
        return view('laundry.reports', compact('daily', 'monthly'));
    }

    public function csv(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w'); fputcsv($out, ['reference','customer_id','status','payment_status','payment_method','total_minor','created_at']);
            LaundryOrder::orderBy('id')->chunk(500, function ($rows) use ($out) { foreach ($rows as $order) fputcsv($out, [$order->reference,$order->customer_id,$order->status,$order->payment_status,$order->payment_method,$order->total_minor,$order->created_at->toIso8601String()]); }); fclose($out);
        }, 'laundry-orders.csv', ['Content-Type' => 'text/csv']);
    }
}
