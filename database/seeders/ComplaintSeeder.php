<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::take(3)->get();
        $buyers = User::where('role', 'buyer')->take(3)->get();

        if ($buyers->isEmpty()) return;

        $complaints = [
            ['subject' => 'Wrong item received', 'description' => 'I received a different item than what I ordered. The product color and size do not match my order.', 'type' => 'order', 'status' => 'open'],
            ['subject' => 'Damaged packaging', 'description' => 'The item arrived with damaged packaging and the product inside was scratched.', 'type' => 'product', 'status' => 'in_review'],
            ['subject' => 'Late delivery', 'description' => 'My order was supposed to arrive 3 days ago but I still haven\'t received it.', 'type' => 'delivery', 'status' => 'resolved', 'resolution' => 'Refund issued and delivery expedited.', 'resolved_at' => now()],
        ];

        foreach ($complaints as $i => $data) {
            Complaint::create(array_merge($data, [
                'buyer_id' => $buyers->random()->id,
                'order_id' => $orders->contains($i) ? $orders[$i]->id : null,
            ]));
        }
    }
}
