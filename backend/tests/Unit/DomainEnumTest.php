<?php

namespace Tests\Unit;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Enums\TransactionInputMethod;
use App\Enums\TransactionStatus;
use PHPUnit\Framework\TestCase;

class DomainEnumTest extends TestCase
{
    public function test_database_backed_values_match_the_domain_contract(): void
    {
        $expected = [
            OperationalUnitType::class => ['TICKETING', 'OUTLET'],
            ItemType::class => ['PRODUCT', 'SERVICE'],
            InventoryLocationType::class => ['WAREHOUSE', 'UNIT'],
            StockDirection::class => ['IN', 'OUT'],
            StockMovementType::class => ['STOCK_IN', 'STOCK_OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'SALE'],
            StockTransferStatus::class => ['PENDING', 'ACCEPTED', 'REJECTED'],
            TransactionStatus::class => ['CART', 'PAID'],
            TransactionInputMethod::class => ['SCANNER', 'MANUAL'],
        ];

        foreach ($expected as $enumClass => $values) {
            $this->assertSame($values, array_column($enumClass::cases(), 'value'), $enumClass);
        }
    }
}
