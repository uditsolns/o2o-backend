<?php

namespace App\Services;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\SealOrderStatus;
use App\Enums\SealStatus;
use App\Enums\TripStatus;
use App\Models\Customer;
use App\Models\Seal;
use App\Models\SealOrder;
use App\Models\Trip;

class DashboardService
{
    public function platformStats(): array
    {
        return [
            'customers' => [
                'total' => Customer::count(),
                'pending' => Customer::where('onboarding_status', CustomerOnboardingStatus::Pending)->count(),
                'submitted' => Customer::where('onboarding_status', CustomerOnboardingStatus::Submitted)->count(),
                'il_approved' => Customer::where('onboarding_status', CustomerOnboardingStatus::IlApproved)->count(),
                'il_parked' => Customer::where('onboarding_status', CustomerOnboardingStatus::IlParked)->count(),
                'completed' => Customer::where('onboarding_status', CustomerOnboardingStatus::Completed)->count(),
            ],
            'orders' => [
                'total' => SealOrder::count(),
                'il_pending' => SealOrder::where('status', SealOrderStatus::IlPending)->count(),
                'il_approved' => SealOrder::where('status', SealOrderStatus::IlApproved)->count(),
                'in_transit' => SealOrder::where('status', SealOrderStatus::InTransit)->count(),
                'completed' => SealOrder::where('status', SealOrderStatus::Completed)->count(),
            ],
            'trips' => [
                'total' => Trip::count(),
                'draft' => Trip::where('status', TripStatus::Draft)->count(),
                'in_transit' => Trip::where('status', TripStatus::InTransit)->count(),
                'at_port' => Trip::where('status', TripStatus::AtPort)->count(),
                'on_vessel' => Trip::where('status', TripStatus::OnVessel)->count(),
                'in_transshipment' => Trip::where('status', TripStatus::InTransshipment)->count(),
                'vessel_arrived' => Trip::where('status', TripStatus::VesselArrived)->count(),
                'out_for_delivery' => Trip::where('status', TripStatus::OutForDelivery)->count(),
                'delivered' => Trip::where('status', TripStatus::Delivered)->count(),
                'completed' => Trip::where('status', TripStatus::Completed)->count(),
            ],
            'seals' => [
                'total' => Seal::count(),
                'in_inventory' => Seal::where('status', SealStatus::InInventory)->count(),
                'assigned' => Seal::where('status', SealStatus::Assigned)->count(),
                'in_transit' => Seal::where('status', SealStatus::InTransit)->count(),
                'used' => Seal::where('status', SealStatus::Used)->count(),
                'tampered' => Seal::where('status', SealStatus::Tampered)->count(),
                'lost' => Seal::where('status', SealStatus::Lost)->count(),
            ],
            'recent_orders' => SealOrder::with('customer:id,company_name,primary_contact_name')
                ->latest('ordered_at')
                ->limit(5)
                ->get(['id', 'order_ref', 'customer_id', 'status', 'total_amount', 'ordered_at']),
            'recent_trips' => Trip::with('seal:id,seal_number,status')
                ->latest()
                ->limit(5)
                ->get(['id', 'seal_id', 'trip_ref', 'customer_id', 'status', 'trip_type', 'dispatch_date', 'created_at']),
            'tampered_seals' => Seal::with('trip')
                ->where('status', SealStatus::Tampered)
                ->latest('last_scan_at')
                ->limit(10)
                ->get(['id', 'seal_number', 'customer_id', 'trip_id', 'last_scan_at']),
        ];
    }

    public function clientStats(int $customerId): array
    {
        return [
            'orders' => [
                'total' => SealOrder::where('customer_id', $customerId)->count(),
                'il_pending' => SealOrder::where('customer_id', $customerId)
                    ->where('status', SealOrderStatus::IlPending)->count(),
                'in_transit' => SealOrder::where('customer_id', $customerId)
                    ->where('status', SealOrderStatus::InTransit)->count(),
                'completed' => SealOrder::where('customer_id', $customerId)
                    ->where('status', SealOrderStatus::Completed)->count(),
            ],
            'seals' => [
                'total' => Seal::where('customer_id', $customerId)->count(),
                'in_inventory' => Seal::where('customer_id', $customerId)
                    ->where('status', SealStatus::InInventory)->count(),
                'assigned' => Seal::where('customer_id', $customerId)
                    ->where('status', SealStatus::Assigned)->count(),
                'tampered' => Seal::where('customer_id', $customerId)
                    ->where('status', SealStatus::Tampered)->count(),
            ],
            'trips' => [
                'total' => Trip::where('customer_id', $customerId)->count(),
                'active' => Trip::where('customer_id', $customerId)
                    ->whereNotIn('status', [TripStatus::Completed, TripStatus::Draft])
                    ->count(),
                'completed' => Trip::where('customer_id', $customerId)
                    ->where('status', TripStatus::Completed)->count(),
                'draft' => Trip::where('customer_id', $customerId)
                    ->where('status', TripStatus::Draft)->count(),
            ],
            'wallet' => $this->clientWalletSummary($customerId),
            'recent_trips' => Trip::where('customer_id', $customerId)
                ->with('seal:id,seal_number,status')
                ->latest()
                ->limit(5)
                ->get(['id', 'seal_id', 'trip_ref', 'status', 'trip_type', 'dispatch_date', 'created_at']),
            'recent_orders' => SealOrder::where('customer_id', $customerId)
                ->latest('ordered_at')
                ->limit(5)
                ->get(['id', 'order_ref', 'status', 'quantity', 'total_amount', 'ordered_at']),
            'tampered_seals' => Seal::where('customer_id', $customerId)
                ->where('status', SealStatus::Tampered)
                ->with('trip:id,trip_ref,status')
                ->latest('last_scan_at')
                ->limit(5)
                ->get(['id', 'seal_number', 'trip_id', 'last_scan_at']),
        ];
    }

    private function clientWalletSummary(int $customerId): array
    {
        $wallet = \App\Models\CustomerWallet::where('customer_id', $customerId)
            ->first(['costing_type', 'cost_balance', 'credit_used', 'credit_capping', 'il_policy_expiry']);

        if (!$wallet) return [];

        return [
            'costing_type' => $wallet->costing_type,
            'cost_balance' => $wallet->cost_balance,
            'credit_used' => $wallet->credit_used,
            'credit_capping' => $wallet->credit_capping,
            'policy_expiry' => $wallet->il_policy_expiry,
        ];
    }

    // ── Reports ───────────────────────────────────────────────────────────────

    public function tripsReport(array $filters): array
    {
        $query = Trip::query()
            ->when(isset($filters['customer_id']),
                fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['status']),
                fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['trip_type']),
                fn($q) => $q->where('trip_type', $filters['trip_type']))
            ->when(isset($filters['transport_mode']),
                fn($q) => $q->where('transport_mode', $filters['transport_mode']))
            ->when(isset($filters['from']),
                fn($q) => $q->whereDate('dispatch_date', '>=', $filters['from']))
            ->when(isset($filters['to']),
                fn($q) => $q->whereDate('dispatch_date', '<=', $filters['to']));

        $trips = (clone $query)->with('seal', 'customer:id,company_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        $summary = $query->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as tampered_seal
        ', [
            TripStatus::Completed->value,
            TripStatus::Completed->value,
            TripStatus::Draft->value,
            TripStatus::Delivered->value,
        ])->first();

        return [
            'summary' => $summary,
            'trips' => $trips,
        ];
    }

    public function sealsReport(array $filters): array
    {
        $query = Seal::query()
            ->when(isset($filters['customer_id']),
                fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['status']),
                fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['sepio_status']),
                fn($q) => $q->where('sepio_status', $filters['sepio_status']))
            ->when(isset($filters['from']),
                fn($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']),
                fn($q) => $q->whereDate('created_at', '<=', $filters['to']));

        $seals = (clone $query)->with('order:id,order_ref', 'customer:id,company_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        $summary = Seal::query()
            ->when(isset($filters['customer_id']),
                fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_inventory,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as tampered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as lost
            ', [
                SealStatus::InInventory->value,
                SealStatus::Assigned->value,
                SealStatus::Used->value,
                SealStatus::Tampered->value,
                SealStatus::Lost->value,
            ])->first();

        return [
            'summary' => $summary,
            'seals' => $seals,
        ];
    }

    public function ordersReport(array $filters): array
    {
        $query = SealOrder::query()
            ->when(isset($filters['customer_id']),
                fn($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(isset($filters['status']),
                fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['payment_type']),
                fn($q) => $q->where('payment_type', $filters['payment_type']))
            ->when(isset($filters['from']),
                fn($q) => $q->whereDate('ordered_at', '>=', $filters['from']))
            ->when(isset($filters['to']),
                fn($q) => $q->whereDate('ordered_at', '<=', $filters['to']));

        $orders = (clone $query)->with('customer:id,company_name', 'orderedBy:id,name')
            ->orderByDesc('ordered_at')
            ->paginate(50);

        $summary = $query->selectRaw('
            COUNT(*) as total_orders,
            SUM(quantity) as total_seals,
            SUM(total_amount) as total_value,
            SUM(seal_cost) as total_seal_cost,
            SUM(freight_amount) as total_freight,
            SUM(gst_amount) as total_gst
        ')->first();

        return [
            'summary' => $summary,
            'orders' => $orders,
        ];
    }
}
