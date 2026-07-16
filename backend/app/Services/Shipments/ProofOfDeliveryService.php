<?php

namespace App\Services\Shipments;

use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProofOfDeliveryService
{
    /**
     * One proof of delivery per shipment — capturing again replaces the
     * prior record (old files removed) rather than accumulating duplicates,
     * since a re-capture means the first attempt was wrong/incomplete.
     */
    public function capture(Shipment $shipment, array $data, UploadedFile $signature, ?UploadedFile $photo): ProofOfDelivery
    {
        if ($shipment->proofOfDelivery) {
            $this->delete($shipment->proofOfDelivery);
        }

        return ProofOfDelivery::query()->create([
            'tenant_id' => $shipment->tenant_id,
            'shipment_id' => $shipment->id,
            'captured_by' => Auth::id(),
            'received_by_name' => $data['received_by_name'],
            'signature_path' => $this->store($signature, 'proof-of-delivery/signatures'),
            'photo_path' => $photo ? $this->store($photo, 'proof-of-delivery/photos') : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function delete(ProofOfDelivery $pod): void
    {
        Storage::disk('public')->delete(array_filter([$pod->signature_path, $pod->photo_path]));
        $pod->delete();
    }

    private function store(UploadedFile $file, string $directory): string
    {
        return $file->storeAs($directory, Str::random(20).'.'.$file->getClientOriginalExtension(), 'public');
    }
}
