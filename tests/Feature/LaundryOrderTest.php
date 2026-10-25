<?php

namespace Tests\Feature;

use App\Models\LaundryOrder;
use App\Models\LaundryService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaundryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function orderPayload(): array
    {
        $wash = LaundryService::where('unit', 'kg')->firstOrFail(); $dry = LaundryService::where('unit', 'item')->firstOrFail();
        return ['pickup_at' => now()->addDay()->format('Y-m-d H:i'), 'delivery_at' => now()->addDays(2)->format('Y-m-d H:i'), 'pickup_address' => '21 Example Street, Central', 'customer_note' => 'Call on arrival', 'payment_method' => 'cash_on_delivery', 'items' => [['service_id' => $wash->id, 'quantity' => '0.500'], ['service_id' => $dry->id, 'quantity' => '2']]];
    }

    private function user(bool $admin = false, bool $staff = false): User
    {
        $user = User::factory()->create(); $user->is_admin = $admin; $user->is_staff = $staff; $user->save(); return $user;
    }

    public function test_registration_creates_an_empty_order_history_and_seeded_catalog(): void
    {
        $user = $this->user();
        $this->assertSame(0, $user->laundryOrders()->count());
        $this->assertSame(2, LaundryService::where('active', true)->where('unit', 'kg')->count());
        $this->assertSame(2, LaundryService::where('active', true)->where('unit', 'item')->count());
    }

    public function test_customer_order_price_is_server_calculated_and_payment_is_separate(): void
    {
        $customer = $this->user(); $payload = $this->orderPayload();
        $this->actingAs($customer)->post(route('laundry.orders.store'), $payload)->assertRedirect();
        $order = LaundryOrder::with('items')->firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('pending', $order->status); $this->assertSame('unpaid', $order->payment_status);
        $expected = intdiv(LaundryService::where('unit','kg')->first()->price_minor, 2) + LaundryService::where('unit','item')->first()->price_minor * 2;
        $this->assertSame($expected, $order->total_minor);
        $this->assertSame($expected, $order->items->sum('line_total_minor'));
        $this->assertDatabaseHas('laundry_order_events', ['laundry_order_id' => $order->id, 'event_type' => 'payment', 'to_value' => 'unpaid']);
    }

    public function test_invalid_quantities_services_and_schedules_are_rejected_without_orders(): void
    {
        $customer = $this->user(); $payload = $this->orderPayload();
        $payload['items'][0]['quantity'] = '0.001';
        $this->actingAs($customer)->post(route('laundry.orders.store'), $payload)->assertSessionHasErrors('items');
        $payload = $this->orderPayload(); $payload['delivery_at'] = now()->subHour()->format('Y-m-d H:i');
        $this->actingAs($customer)->post(route('laundry.orders.store'), $payload)->assertSessionHasErrors('delivery_at');
        $this->assertDatabaseCount('laundry_orders', 0);
    }

    public function test_only_owner_or_assigned_staff_can_view_order(): void
    {
        $customer = $this->user(); $other = $this->user(); $staff = $this->user(staff: true);
        $this->actingAs($customer)->post(route('laundry.orders.store'), $this->orderPayload())->assertRedirect();
        $order = LaundryOrder::firstOrFail();
        $this->actingAs($other)->get(route('laundry.orders.show', $order))->assertForbidden();
        $this->actingAs($staff)->get(route('laundry.orders.show', $order))->assertForbidden();
        $order->update(['assigned_staff_id' => $staff->id]);
        $this->actingAs($staff)->get(route('laundry.orders.show', $order))->assertOk();
        $this->actingAs($customer)->get(route('laundry.orders.show', $order))->assertOk();
    }

    public function test_staff_updates_assigned_orders_and_invalid_transitions_are_rejected(): void
    {
        $customer = $this->user(); $staff = $this->user(staff: true); $other = $this->user(staff: true);
        $this->actingAs($customer)->post(route('laundry.orders.store'), $this->orderPayload())->assertRedirect(); $order = LaundryOrder::firstOrFail();
        $order->update(['assigned_staff_id' => $staff->id]);
        $this->actingAs($other)->patch(route('laundry.orders.update',$order), ['status' => 'confirmed'])->assertForbidden();
        $this->actingAs($staff)->patch(route('laundry.orders.update',$order), ['status' => 'picked_up'])->assertSessionHasErrors('status');
        $this->actingAs($staff)->patch(route('laundry.orders.update',$order), ['status' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('laundry_order_events', ['laundry_order_id' => $order->id, 'event_type' => 'status', 'from_value' => 'pending', 'to_value' => 'confirmed', 'actor_user_id' => $staff->id]);
        $this->actingAs($staff)->patch(route('laundry.orders.update',$order), ['payment_status' => 'paid'])->assertRedirect();
        $order->refresh(); $this->assertSame('confirmed', $order->status); $this->assertSame('paid', $order->payment_status);
    }

    public function test_customer_can_cancel_only_eligible_own_order(): void
    {
        $customer = $this->user(); $other = $this->user();
        $this->actingAs($customer)->post(route('laundry.orders.store'), $this->orderPayload())->assertRedirect(); $order = LaundryOrder::firstOrFail();
        $this->actingAs($other)->patch(route('laundry.orders.cancel',$order))->assertForbidden();
        $this->actingAs($customer)->patch(route('laundry.orders.cancel',$order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status); $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($customer)->patch(route('laundry.orders.cancel',$order))->assertSessionHasErrors('status');
    }

    public function test_admin_can_assign_staff_manage_catalog_and_view_reports(): void
    {
        $admin = $this->user(admin: true); $staff = $this->user(staff: true);
        $this->actingAs($admin)->get(route('laundry.staff'))->assertOk();
        $this->actingAs($admin)->get(route('laundry.services'))->assertOk();
        $this->actingAs($admin)->get(route('laundry.reports'))->assertOk();
        $this->actingAs($admin)->get(route('laundry.csv'))->assertOk();
        $this->actingAs($admin)->post(route('laundry.services.store'), ['name' => 'Premium Press', 'description' => 'Careful pressing', 'unit' => 'item', 'price' => '12500.50', 'active' => '1'])->assertRedirect();
        $this->assertDatabaseHas('laundry_services', ['name' => 'Premium Press', 'price_minor' => 1250050, 'unit' => 'item']);
        $wash = LaundryService::where('name', 'Wash & Fold')->firstOrFail();
        $this->actingAs($admin)->put(route('laundry.services.update', $wash), ['name' => 'Wash & Fold', 'description' => 'Updated', 'unit' => 'kg', 'price' => '9000.00', 'active' => '0'])->assertRedirect();
        $this->assertDatabaseHas('laundry_services', ['id' => $wash->id, 'price_minor' => 900000, 'active' => 0]);
        $this->actingAs($staff)->get(route('laundry.services'))->assertForbidden();
        $this->assertSame(1, User::where('is_staff', true)->count());
    }
}
