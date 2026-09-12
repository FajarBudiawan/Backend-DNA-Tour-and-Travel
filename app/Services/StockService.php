<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function create(array $data): Stock
    {
        $sizes = $data['sizes'] ?? [];

        unset($data['sizes']);

        $data['code'] = Stock::generateCode();

        return DB::transaction(function () use ($data, $sizes) {
            $stock = Stock::create($data);

            $this->createSizes($stock, $sizes);

            $stock = $stock->refresh();

            $this->validateSizeConsistency($stock);

            return $stock->load('sizes');
        });
    }

    public function update(
        Stock $stock,
        array $data
    ): Stock {
        return DB::transaction(function () use ($stock, $data) {
            $stock->refresh();

            $hasSizes = $stock->sizes()->exists();
            $sizesProvided = array_key_exists('sizes', $data);
            $quantityProvided = array_key_exists('quantity', $data);

            /*
             * Stock tanpa ukuran
             */
            if (! $hasSizes) {
                if ($sizesProvided && ! empty($data['sizes'])) {
                    throw new \InvalidArgumentException(
                        "Stok {$stock->code} tidak menggunakan ukuran."
                    );
                }

                if ($quantityProvided) {
                    $newQuantity = (int) $data['quantity'];
                    $delta = $newQuantity - $stock->quantity;

                    if ($delta !== 0) {
                        $stock->quantity = $newQuantity;
                        $stock->save();

                        StockTransaction::create([
                            'stock_id' => $stock->id,
                            'type' => 'adjustment',
                            'quantity' => $delta,
                            'size' => null,
                            'reference_type' => null,
                            'reference_id' => null,
                        ]);
                    }

                    unset($data['quantity']);
                }

                unset($data['sizes']);

                $stock->fill($data);
                $stock->save();

                return $stock->refresh()->load('sizes');
            }

            /*
             * Stock dengan ukuran
             */
            if ($quantityProvided) {
                throw new \InvalidArgumentException(
                    "Quantity total untuk stok {$stock->code} dihitung dari rincian ukuran."
                );
            }

            if ($sizesProvided) {
                $this->updateSizes(
                    $stock,
                    $data['sizes'] ?? []
                );

                unset($data['sizes']);
            }

            $stock->fill($data);
            $stock->save();

            $stock = $stock->refresh();

            $this->validateSizeConsistency($stock);

            return $stock->load('sizes');
        });
    }

    public function add(
        Stock $stock,
        int $quantity,
        ?string $size = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): Stock {
        $this->validateQuantity($quantity);
        $this->validateSize($stock, $size);

        return DB::transaction(function () use (
            $stock,
            $quantity,
            $size,
            $referenceType,
            $referenceId
        ) {
            $stock->quantity += $quantity;
            $stock->save();

            if ($size !== null) {
                $stockSize = $stock->sizes()
                    ->where('size', $size)
                    ->first();

                if ($stockSize === null) {
                    throw new \InvalidArgumentException(
                        "Ukuran {$size} tidak tersedia untuk stok {$stock->code}."
                    );
                }

                $stockSize->quantity += $quantity;
                $stockSize->save();
            }

            StockTransaction::create([
                'stock_id' => $stock->id,
                'type' => 'in',
                'quantity' => $quantity,
                'size' => $size,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $stock = $stock->refresh();

            $this->validateSizeConsistency($stock);

            return $stock;
        });
    }

    public function remove(
        Stock $stock,
        int $quantity,
        ?string $size = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): Stock {
        $this->validateQuantity($quantity);
        $this->validateSize($stock, $size);

        return DB::transaction(function () use (
            $stock,
            $quantity,
            $size,
            $referenceType,
            $referenceId
        ) {
            if ($size !== null) {
                $stockSize = $stock->sizes()
                    ->where('size', $size)
                    ->first();

                if ($stockSize === null || $stockSize->quantity < $quantity) {
                    throw new \InvalidArgumentException(
                        "Stok ukuran {$size} tidak mencukupi."
                    );
                }

                $stockSize->quantity -= $quantity;
                $stockSize->save();
            }

            if ($stock->quantity < $quantity) {
                throw new \InvalidArgumentException(
                    "Stok {$stock->code} tidak mencukupi."
                );
            }

            $stock->quantity -= $quantity;
            $stock->save();

            StockTransaction::create([
                'stock_id' => $stock->id,
                'type' => 'out',
                'quantity' => $quantity,
                'size' => $size,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $stock = $stock->refresh();

            $this->validateSizeConsistency($stock);

            return $stock;
        });
    }

    public function adjust(
        Stock $stock,
        int $delta,
        ?string $size = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): Stock {
        if ($delta === 0) {
            throw new \InvalidArgumentException(
                'Adjustment tidak boleh 0.'
            );
        }

        $this->validateSize($stock, $size);

        return DB::transaction(function () use (
            $stock,
            $delta,
            $size,
            $referenceType,
            $referenceId
        ) {
            if ($size !== null) {
                $stockSize = $stock->sizes()
                    ->where('size', $size)
                    ->first();

                if ($stockSize === null) {
                    throw new \InvalidArgumentException(
                        "Ukuran {$size} tidak tersedia untuk stok {$stock->code}."
                    );
                }

                $newSizeQuantity = $stockSize->quantity + $delta;

                if ($newSizeQuantity < 0) {
                    throw new \InvalidArgumentException(
                        "Adjustment menyebabkan stok ukuran {$size} menjadi negatif."
                    );
                }

                $stockSize->quantity = $newSizeQuantity;
                $stockSize->save();
            }

            $newQuantity = $stock->quantity + $delta;

            if ($newQuantity < 0) {
                throw new \InvalidArgumentException(
                    "Adjustment menyebabkan stok {$stock->code} menjadi negatif."
                );
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            StockTransaction::create([
                'stock_id' => $stock->id,
                'type' => 'adjustment',
                'quantity' => $delta,
                'size' => $size,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            $stock = $stock->refresh();

            $this->validateSizeConsistency($stock);

            return $stock;
        });
    }

    private function createSizes(
        Stock $stock,
        array $sizes
    ): void {
        if (empty($sizes)) {
            return;
        }

        $totalQuantity = 0;
        $usedSizes = [];

        foreach ($sizes as $item) {
            if (
                ! is_array($item) ||
                ! isset($item['size'], $item['quantity'])
            ) {
                throw new \InvalidArgumentException(
                    'Format ukuran stok tidak valid.'
                );
            }

            $size = strtoupper(trim((string) $item['size']));
            $quantity = (int) $item['quantity'];

            if ($size === '') {
                throw new \InvalidArgumentException(
                    'Ukuran tidak boleh kosong.'
                );
            }

            if ($quantity < 0) {
                throw new \InvalidArgumentException(
                    "Quantity ukuran {$size} tidak boleh negatif."
                );
            }

            if (in_array($size, $usedSizes, true)) {
                throw new \InvalidArgumentException(
                    "Ukuran {$size} tidak boleh didaftarkan dua kali."
                );
            }

            $usedSizes[] = $size;
            $totalQuantity += $quantity;

            $stock->sizes()->create([
                'size' => $size,
                'quantity' => $quantity,
            ]);
        }

        if ($totalQuantity !== $stock->quantity) {
            throw new \InvalidArgumentException(
                'Total quantity berdasarkan ukuran harus sama dengan quantity stok.'
            );
        }
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Quantity harus lebih dari 0.'
            );
        }
    }

    private function validateSize(
        Stock $stock,
        ?string $size
    ): void {
        $hasSizes = $stock->sizes()->exists();

        if ($hasSizes && $size === null) {
            throw new \InvalidArgumentException(
                "Stok {$stock->code} memiliki ukuran. Size wajib diisi."
            );
        }

        if (! $hasSizes && $size !== null) {
            throw new \InvalidArgumentException(
                "Stok {$stock->code} tidak menggunakan ukuran."
            );
        }

        if ($size !== null) {
            $size = strtoupper(trim($size));

            $exists = $stock->sizes()
                ->where('size', $size)
                ->exists();

            if (! $exists) {
                throw new \InvalidArgumentException(
                    "Ukuran {$size} tidak tersedia untuk stok {$stock->code}."
                );
            }
        }
    }

    private function updateSizes(
        Stock $stock,
        array $sizes
    ): void {
        $existingSizes = $stock->sizes()
            ->get()
            ->keyBy('size');

        $incomingSizes = [];

        foreach ($sizes as $item) {
            if (
                ! is_array($item) ||
                ! isset($item['size'], $item['quantity'])
            ) {
                throw new \InvalidArgumentException(
                    'Format ukuran stok tidak valid.'
                );
            }

            $size = strtoupper(trim((string) $item['size']));
            $quantity = (int) $item['quantity'];

            if ($size === '') {
                throw new \InvalidArgumentException(
                    'Ukuran tidak boleh kosong.'
                );
            }

            if ($quantity < 0) {
                throw new \InvalidArgumentException(
                    "Quantity ukuran {$size} tidak boleh negatif."
                );
            }

            if (isset($incomingSizes[$size])) {
                throw new \InvalidArgumentException(
                    "Ukuran {$size} tidak boleh dikirim dua kali."
                );
            }

            if (! $existingSizes->has($size)) {
                throw new \InvalidArgumentException(
                    "Ukuran {$size} tidak tersedia untuk stok {$stock->code}."
                );
            }

            $incomingSizes[$size] = $quantity;
        }

        if (count($incomingSizes) !== $existingSizes->count()) {
            throw new \InvalidArgumentException(
                'Semua ukuran yang sudah terdaftar harus dikirim saat update.'
            );
        }

        foreach ($existingSizes as $size => $stockSize) {
            $oldQuantity = $stockSize->quantity;
            $newQuantity = $incomingSizes[$size];
            $delta = $newQuantity - $oldQuantity;

            if ($delta === 0) {
                continue;
            }

            $stockSize->quantity = $newQuantity;
            $stockSize->save();

            StockTransaction::create([
                'stock_id' => $stock->id,
                'type' => 'adjustment',
                'quantity' => $delta,
                'size' => $size,
                'reference_type' => null,
                'reference_id' => null,
            ]);
        }

        $stock->quantity = array_sum($incomingSizes);
        $stock->save();
    }

    private function validateSizeConsistency(Stock $stock): void
    {
        $hasSizes = $stock->sizes()->exists();

        if (! $hasSizes) {
            return;
        }

        $sizeTotal = (int) $stock->sizes()->sum('quantity');

        if ($sizeTotal !== $stock->quantity) {
            throw new \InvalidArgumentException(
                "Total stok {$stock->code} tidak sesuai dengan total stok berdasarkan ukuran."
            );
        }
    }
}