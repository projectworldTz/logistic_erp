<?php

namespace App\Services\Analytics;

use App\Enums\ShipmentStatus;
use App\Enums\TrackingEventType;
use App\Models\Shipment;
use App\Models\TrackingEvent;

/**
 * A statistical (not AI) delay-risk score: given a shipment still in
 * progress, look at how often *historically completed* shipments with
 * the same route (or, failing that, the same mode+direction) actually
 * arrived after their ETA. This is forward-looking — unlike
 * Shipment::is_at_risk (ShipmentResource), which only flags an ETA that
 * has already passed — so it's meaningful the moment a shipment is
 * booked, not just once it's running late.
 */
class DelayRiskPredictor
{
    private const MIN_SAMPLE_SIZE = 3;

    public function predict(Shipment $shipment): array
    {
        $route = $this->historicalDelayRate(
            fn ($query) => $query
                ->where('mode', $shipment->mode)
                ->where('direction', $shipment->direction)
                ->where('origin_port', $shipment->origin_port)
                ->where('destination_port', $shipment->destination_port)
        );

        if ($route['sample_size'] >= self::MIN_SAMPLE_SIZE && $shipment->origin_port && $shipment->destination_port) {
            return $this->format($route, 'route');
        }

        $modeDirection = $this->historicalDelayRate(
            fn ($query) => $query
                ->where('mode', $shipment->mode)
                ->where('direction', $shipment->direction)
        );

        if ($modeDirection['sample_size'] >= self::MIN_SAMPLE_SIZE) {
            return $this->format($modeDirection, 'mode_direction');
        }

        return [
            'risk_score' => null,
            'risk_level' => 'insufficient_data',
            'sample_size' => $modeDirection['sample_size'],
            'basis' => 'insufficient_data',
        ];
    }

    /**
     * @param  \Closure(\Illuminate\Database\Eloquent\Builder<Shipment>): \Illuminate\Database\Eloquent\Builder<Shipment>  $scope
     */
    private function historicalDelayRate(\Closure $scope): array
    {
        $shipments = $scope(
            Shipment::query()->whereIn('status', [ShipmentStatus::Arrived, ShipmentStatus::Delivered])
                ->whereNotNull('eta')
        )->get(['id', 'eta']);

        if ($shipments->isEmpty()) {
            return ['sample_size' => 0, 'delayed_count' => 0];
        }

        $arrivals = TrackingEvent::query()
            ->whereIn('trackable_id', $shipments->pluck('id'))
            ->where('trackable_type', Shipment::class)
            ->whereIn('event_type', [TrackingEventType::Arrived, TrackingEventType::Delivered])
            ->get(['trackable_id', 'occurred_at'])
            ->groupBy('trackable_id')
            ->map(fn ($events) => $events->max('occurred_at'));

        $delayedCount = 0;
        $sampleSize = 0;

        foreach ($shipments as $s) {
            $arrivedAt = $arrivals->get($s->id);
            if (! $arrivedAt) {
                continue;
            }

            $sampleSize++;
            if ($arrivedAt->greaterThan($s->eta)) {
                $delayedCount++;
            }
        }

        return ['sample_size' => $sampleSize, 'delayed_count' => $delayedCount];
    }

    private function format(array $stats, string $basis): array
    {
        $rate = round(($stats['delayed_count'] / $stats['sample_size']) * 100, 1);

        return [
            'risk_score' => $rate,
            'risk_level' => match (true) {
                $rate < 20 => 'low',
                $rate < 50 => 'medium',
                default => 'high',
            },
            'sample_size' => $stats['sample_size'],
            'basis' => $basis,
        ];
    }
}
