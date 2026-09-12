<?php

namespace App\Services;

use App\Models\RegistrationEquipment;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class RegistrationEquipmentService
{
    public function __construct(
        private StockService $stockService
    ) {
    }

    public function syncEquipment(
        RegistrationEquipment $equipment,
        array $newData
    ): RegistrationEquipment {
        return DB::transaction(function () use ($equipment, $newData) {
            $equipment->refresh();

            // =========================================================
            // STATE LAMA
            // =========================================================
            $oldIsReceived = (bool) $equipment->is_received;
            $oldStockId = $equipment->stock_id;
            $oldSize = $equipment->size;

            // =========================================================
            // STATE BARU
            // =========================================================
            $newIsReceived = array_key_exists('is_received', $newData)
                ? (bool) $newData['is_received']
                : $oldIsReceived;

            $newStockId = array_key_exists('stock_id', $newData)
                ? $newData['stock_id']
                : $oldStockId;

            $newSize = array_key_exists('size', $newData)
                ? $newData['size']
                : $oldSize;

            /*
             * =========================================================
             * CASE 1: false → true
             *
             * Barang pertama kali diterima.
             * Stok dikurangi 1.
             * =========================================================
             */
            if (! $oldIsReceived && $newIsReceived) {
                if ($newStockId === null) {
                    throw new \InvalidArgumentException(
                        'Stock perlengkapan wajib dipilih sebelum barang diterima.'
                    );
                }

                $stock = Stock::findOrFail($newStockId);

                $this->stockService->remove(
                    $stock,
                    1,
                    $newSize,
                    'registration',
                    $equipment->registration_id
                );

                $newData['received_at'] = now();
            }

            /*
             * =========================================================
             * CASE 2: true → false
             *
             * Barang yang sebelumnya diterima dikembalikan.
             * Stok dikembalikan 1.
             * =========================================================
             */
            if ($oldIsReceived && ! $newIsReceived) {
                if ($oldStockId === null) {
                    throw new \InvalidArgumentException(
                        'Stock perlengkapan sebelumnya tidak ditemukan.'
                    );
                }

                $stock = Stock::findOrFail($oldStockId);

                $this->stockService->add(
                    $stock,
                    1,
                    $oldSize,
                    'registration',
                    $equipment->registration_id
                );

                $newData['received_at'] = null;
            }

            /*
             * =========================================================
             * CASE 3: true → true
             *
             * Barang tetap diterima tetapi stock atau size berubah.
             *
             * Logic:
             * 1. Kembalikan stok lama
             * 2. Kurangi stok baru
             * =========================================================
             */
            if ($oldIsReceived && $newIsReceived) {
                $stockChanged = $oldStockId !== $newStockId;
                $sizeChanged = $oldSize !== $newSize;

                if ($stockChanged || $sizeChanged) {
                    if ($oldStockId === null) {
                        throw new \InvalidArgumentException(
                            'Stock lama tidak tersedia untuk equipment yang sudah diterima.'
                        );
                    }

                    if ($newStockId === null) {
                        throw new \InvalidArgumentException(
                            'Stock baru wajib diisi untuk equipment yang sudah diterima.'
                        );
                    }

                    $oldStock = Stock::findOrFail($oldStockId);
                    $newStock = Stock::findOrFail($newStockId);

                    // Kembalikan stok lama
                    $this->stockService->add(
                        $oldStock,
                        1,
                        $oldSize,
                        'registration',
                        $equipment->registration_id
                    );

                    // Kurangi stok baru
                    $this->stockService->remove(
                        $newStock,
                        1,
                        $newSize,
                        'registration',
                        $equipment->registration_id
                    );
                }
            }

            /*
             * =========================================================
             * SIMPAN PERUBAHAN EQUIPMENT
             * =========================================================
             */
            $equipment->fill($newData);

            // Pastikan nilainya mengikuti state baru
            $equipment->is_received = $newIsReceived;

            $equipment->save();

            return $equipment->refresh();
        });
    }

    public function updateEquipment(
        RegistrationEquipment $equipment,
        array $newData
    ): RegistrationEquipment {
        return $this->syncEquipment($equipment, $newData);
    }

    public function deleteEquipment(
        RegistrationEquipment $equipment
    ): void {
        DB::transaction(function () use ($equipment) {
            $equipment->refresh();

            if ($equipment->is_received) {
                if ($equipment->stock_id === null) {
                    throw new \InvalidArgumentException(
                        'Stock perlengkapan tidak ditemukan untuk equipment yang sudah diterima.'
                    );
                }

                $stock = Stock::findOrFail($equipment->stock_id);

                $this->stockService->add(
                    $stock,
                    1,
                    $equipment->size,
                    'registration',
                    $equipment->registration_id
                );
            }

            $equipment->delete();
        });
    }
}