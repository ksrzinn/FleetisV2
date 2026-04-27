<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Finance\Models\FuelRecord;
use App\Modules\Finance\Models\MaintenanceRecord;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Operations\Models\Freight;
use Illuminate\Support\Collection;

class VehicleReportService
{
    /**
     * All vehicles (kind=vehicle) for the authenticated company with aggregated metrics
     * computed via correlated subqueries to avoid N+1.
     */
    public function allVehiclesWithMetrics(): Collection
    {
        return Vehicle::query()
            ->where('kind', 'vehicle')
            ->with('vehicleType')
            ->selectRaw('vehicles.*')
            ->selectRaw('(
                SELECT COALESCE(SUM(f.freight_value), 0)
                FROM freights f
                WHERE f.vehicle_id = vehicles.id
                  AND f.freight_value IS NOT NULL
                  AND f.company_id = vehicles.company_id
                  AND f.deleted_at IS NULL
            ) AS revenue')
            ->selectRaw('(
                SELECT COALESCE(SUM(fr.total_cost), 0)
                FROM fuel_records fr
                WHERE fr.vehicle_id = vehicles.id
            ) AS fuel_cost')
            ->selectRaw('(
                SELECT COALESCE(SUM(mr.cost), 0)
                FROM maintenance_records mr
                WHERE mr.vehicle_id = vehicles.id
            ) AS maintenance_cost')
            ->selectRaw('(
                SELECT COUNT(*)
                FROM freights f2
                WHERE f2.vehicle_id = vehicles.id
                  AND f2.deleted_at IS NULL
            ) AS freight_count')
            ->selectRaw('(
                SELECT COALESCE(SUM(f3.distance_km), 0)
                FROM freights f3
                WHERE f3.vehicle_id = vehicles.id
                  AND f3.deleted_at IS NULL
            ) AS total_km')
            ->orderBy('license_plate')
            ->get();
    }

    /**
     * Aggregated metrics for a single vehicle using separate Eloquent queries.
     *
     * @return array{revenue: string, fuel_cost: string, maintenance_cost: string, freight_count: int, total_km: string}
     */
    public function vehicleMetrics(Vehicle $vehicle): array
    {
        $revenue = Freight::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('freight_value')
            ->sum('freight_value');

        $fuelCost = FuelRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->sum('total_cost');

        $maintenanceCost = MaintenanceRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->sum('cost');

        $freightCount = Freight::query()
            ->where('vehicle_id', $vehicle->id)
            ->count();

        $totalKm = Freight::query()
            ->where('vehicle_id', $vehicle->id)
            ->sum('distance_km');

        return [
            'revenue'          => (string) $revenue,
            'fuel_cost'        => (string) $fuelCost,
            'maintenance_cost' => (string) $maintenanceCost,
            'freight_count'    => (int) $freightCount,
            'total_km'         => (string) $totalKm,
        ];
    }

    /**
     * Most recent N freights for a specific vehicle.
     */
    public function recentFreights(Vehicle $vehicle, int $limit = 10): Collection
    {
        return Freight::query()
            ->where('vehicle_id', $vehicle->id)
            ->select(['id', 'client_id', 'status', 'freight_value', 'distance_km', 'created_at'])
            ->with('client:id,name')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Total outstanding receivables linked to freights for the authenticated company.
     */
    public function freightsReceivableOutstanding(): string
    {
        return (string) Receivable::query()
            ->whereNotNull('freight_id')
            ->whereIn('status', ['open', 'partially_paid', 'overdue'])
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) AS total')
            ->value('total');
    }
}
