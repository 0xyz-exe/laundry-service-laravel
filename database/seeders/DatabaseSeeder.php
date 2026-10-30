<?php

namespace Database\Seeders;

use App\Models\LaundryOrder;
use App\Models\LaundryOrderEvent;
use App\Models\LaundryOrderItem;
use App\Models\LaundryService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            foreach ([
                ['name' => 'Wash & Fold', 'unit' => 'kg', 'price_minor' => 800000, 'description' => 'Wash, dry and fold, per kilogram.'],
                ['name' => 'Express Wash', 'unit' => 'kg', 'price_minor' => 1200000, 'description' => 'Priority wash and fold, per kilogram.'],
                ['name' => 'Dry Cleaning', 'unit' => 'item', 'price_minor' => 2500000, 'description' => 'Garment dry cleaning, per item.'],
                ['name' => 'Bedding Service', 'unit' => 'item', 'price_minor' => 4000000, 'description' => 'Large bedding item cleaning, per item.'],
            ] as $service) {
                LaundryService::firstOrCreate(['name' => $service['name']], $service + ['active' => true]);
            }
            return;
        }

        // 1. Users
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name' => 'Alexander Wright',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->is_admin = true;
        $admin->is_staff = true;
        $admin->save();

        $staffHendra = User::firstOrCreate(
            ['email' => 'hendra.k@freshclean.test'],
            ['name' => 'Hendra Kurniawan', 'password' => Hash::make('password')]
        );
        $staffHendra->is_admin = false;
        $staffHendra->is_staff = true;
        $staffHendra->save();

        $staffDewi = User::firstOrCreate(
            ['email' => 'dewi.lestari@freshclean.test'],
            ['name' => 'Dewi Lestari', 'password' => Hash::make('password')]
        );
        $staffDewi->is_admin = false;
        $staffDewi->is_staff = true;
        $staffDewi->save();

        $custClara = User::firstOrCreate(
            ['email' => 'clara.d@consumer.test'],
            ['name' => 'Clara Danuwijaya', 'password' => Hash::make('password')]
        );

        $custBambang = User::firstOrCreate(
            ['email' => 'bambang.s@consumer.test'],
            ['name' => 'Bambang Soedirman', 'password' => Hash::make('password')]
        );

        // 2. Services
        $svcWash = LaundryService::updateOrCreate(['name' => 'Wash & Fold'], [
            'unit' => 'kg',
            'price_minor' => 800000,
            'description' => 'Gentle detergent wash, warm tumble dry, and neat hand folding, per kilogram.',
            'active' => true,
        ]);

        $svcExpress = LaundryService::updateOrCreate(['name' => 'Express Wash'], [
            'unit' => 'kg',
            'price_minor' => 1200000,
            'description' => 'Priority 6-hour turnaround wash and steam press, per kilogram.',
            'active' => true,
        ]);

        $svcDry = LaundryService::updateOrCreate(['name' => 'Dry Cleaning'], [
            'unit' => 'item',
            'price_minor' => 2500000,
            'description' => 'Executive suits, blazers, and delicate evening garments solvent clean & steam press, per item.',
            'active' => true,
        ]);

        $svcBedding = LaundryService::updateOrCreate(['name' => 'Bedding Service'], [
            'unit' => 'item',
            'price_minor' => 4000000,
            'description' => 'Large king/queen duvet, thermal blanket & comforter sanitization, per item.',
            'active' => true,
        ]);

        // 3. Orders (local / demo environment only; excluded during tests)
        if (app()->environment('testing')) {
            return;
        }

        // Order 1: Alexander Wright - Active Processing
        $order1 = LaundryOrder::firstOrCreate(['reference' => 'LND-20261030-9182'], [
            'customer_id' => $admin->id,
            'assigned_staff_id' => $staffHendra->id,
            'pickup_at' => Carbon::now()->subHours(4),
            'delivery_at' => Carbon::now()->addHours(20),
            'pickup_address' => 'Senopati Penthouse Suite 18A, Jl. Senopati No. 44, Jakarta Selatan',
            'customer_note' => 'Delicate silk shirts in separate mesh bag please. Medium starch on formal business collars.',
            'status' => 'processing',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'total_minor' => 4900000,
            'created_at' => Carbon::now()->subHours(5),
        ]);

        if ($order1->wasRecentlyCreated) {
            $order1->items()->create([
                'laundry_service_id' => $svcWash->id,
                'service_name' => $svcWash->name,
                'unit' => $svcWash->unit,
                'quantity_units' => 3000,
                'unit_price_minor' => 800000,
                'line_total_minor' => 2400000,
            ]);
            $order1->items()->create([
                'laundry_service_id' => $svcDry->id,
                'service_name' => $svcDry->name,
                'unit' => $svcDry->unit,
                'quantity_units' => 1,
                'unit_price_minor' => 2500000,
                'line_total_minor' => 2500000,
            ]);

            LaundryOrderEvent::create(['laundry_order_id' => $order1->id, 'actor_user_id' => $admin->id, 'event_type' => 'status', 'from_value' => null, 'to_value' => 'pending', 'note' => 'Order submitted by customer', 'created_at' => Carbon::now()->subHours(5)]);
            LaundryOrderEvent::create(['laundry_order_id' => $order1->id, 'actor_user_id' => $admin->id, 'event_type' => 'status', 'from_value' => 'pending', 'to_value' => 'confirmed', 'note' => 'Order confirmed and scheduled for driver pickup', 'created_at' => Carbon::now()->subHours(4)->subMinutes(45)]);
            LaundryOrderEvent::create(['laundry_order_id' => $order1->id, 'actor_user_id' => $admin->id, 'event_type' => 'assignment', 'from_value' => 'unassigned', 'to_value' => (string)$staffHendra->id, 'note' => 'Assigned to senior operator Hendra', 'created_at' => Carbon::now()->subHours(4)->subMinutes(30)]);
            LaundryOrderEvent::create(['laundry_order_id' => $order1->id, 'actor_user_id' => $staffHendra->id, 'event_type' => 'status', 'from_value' => 'confirmed', 'to_value' => 'picked_up', 'note' => 'Laundry bags picked up from building concierge', 'created_at' => Carbon::now()->subHours(3)]);
            LaundryOrderEvent::create(['laundry_order_id' => $order1->id, 'actor_user_id' => $staffHendra->id, 'event_type' => 'status', 'from_value' => 'picked_up', 'to_value' => 'processing', 'note' => 'Weighed, tagged, and loaded into industrial washer #02', 'created_at' => Carbon::now()->subHour()]);
        }

        // Order 2: Clara Danuwijaya - Ready for Delivery
        $order2 = LaundryOrder::firstOrCreate(['reference' => 'LND-20261030-8431'], [
            'customer_id' => $custClara->id,
            'assigned_staff_id' => $staffDewi->id,
            'pickup_at' => Carbon::now()->subHours(8),
            'delivery_at' => Carbon::now()->addHours(4),
            'pickup_address' => 'Sudirman Park Tower B Unit 1204, Jl. K.H. Mas Mansyur Kav. 35',
            'customer_note' => 'Please hang blazers on wooden hangers provided.',
            'status' => 'ready',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'total_minor' => 6500000,
            'created_at' => Carbon::now()->subHours(9),
        ]);
        if ($order2->wasRecentlyCreated) {
            $order2->items()->create(['laundry_service_id' => $svcDry->id, 'service_name' => $svcDry->name, 'unit' => 'item', 'quantity_units' => 1, 'unit_price_minor' => 2500000, 'line_total_minor' => 2500000]);
            $order2->items()->create(['laundry_service_id' => $svcBedding->id, 'service_name' => $svcBedding->name, 'unit' => 'item', 'quantity_units' => 1, 'unit_price_minor' => 4000000, 'line_total_minor' => 4000000]);
        }

        // Order 3: Alexander Wright - Delivered
        $order3 = LaundryOrder::firstOrCreate(['reference' => 'LND-20261029-7710'], [
            'customer_id' => $admin->id,
            'assigned_staff_id' => $staffHendra->id,
            'pickup_at' => Carbon::now()->subDays(2),
            'delivery_at' => Carbon::now()->subDay(),
            'pickup_address' => 'SCBD Suites Tower 1 Unit 09A, Jakarta Selatan',
            'customer_note' => 'Regular weekly family laundry.',
            'status' => 'delivered',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'paid',
            'total_minor' => 4000000,
            'created_at' => Carbon::now()->subDays(2),
        ]);
        if ($order3->wasRecentlyCreated) {
            $order3->items()->create(['laundry_service_id' => $svcWash->id, 'service_name' => $svcWash->name, 'unit' => 'kg', 'quantity_units' => 5000, 'unit_price_minor' => 800000, 'line_total_minor' => 4000000]);
        }

        // Order 4: Bambang Soedirman - Pending Confirmation
        $order4 = LaundryOrder::firstOrCreate(['reference' => 'LND-20261030-9945'], [
            'customer_id' => $custBambang->id,
            'assigned_staff_id' => null,
            'pickup_at' => Carbon::now()->addHours(2),
            'delivery_at' => Carbon::now()->addDays(2),
            'pickup_address' => 'Jl. Kemang Raya No. 12B, Mampang Prapatan, Jakarta Selatan',
            'customer_note' => 'Call before arrival. Security gate code is 4492.',
            'status' => 'pending',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'total_minor' => 3600000,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);
        if ($order4->wasRecentlyCreated) {
            $order4->items()->create(['laundry_service_id' => $svcExpress->id, 'service_name' => $svcExpress->name, 'unit' => 'kg', 'quantity_units' => 3000, 'unit_price_minor' => 1200000, 'line_total_minor' => 3600000]);
        }
    }
}
