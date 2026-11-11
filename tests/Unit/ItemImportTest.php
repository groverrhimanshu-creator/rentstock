<?php

namespace Tests\Unit;

use App\Support\ItemImport;
use PHPUnit\Framework\TestCase;

class ItemImportTest extends TestCase
{
    private function run_(array $body, array $existing = []): array
    {
        $head = ['Product Name', 'Category', 'Brand', 'Unit', 'HSN Code', 'Rental Price', 'Sale Price', 'CGST', 'SGST', 'IGST', 'Parameterized', 'Generate Barcode', 'Maintain Stock'];
        return ItemImport::validate(ItemImport::rows(array_merge([$head], $body)), $existing, [], [], []);
    }

    public function test_barcode_yes_makes_a_barcoded_item_even_when_parameterized_is_yes(): void
    {
        $r = $this->run_([['A', 'C', 'B', 'Nos', '997315', '', '', '', '', '', 'Yes', 'Yes', 'Yes']])[0];
        $this->assertSame('ok', $r['status']);
        $this->assertTrue($r['v']['tracked']);
    }

    public function test_barcode_no_makes_a_quantity_item_and_maintain_stock_follows_the_sheet(): void
    {
        $r = $this->run_([['A', 'C', 'B', 'Nos', '997315', '', '', '', '', '', 'Yes', 'No', 'No']])[0];
        $this->assertFalse($r['v']['tracked']);
        $this->assertFalse($r['v']['maintain']);
    }

    public function test_barcoded_items_are_always_kept_in_stock(): void
    {
        $r = $this->run_([['A', 'C', 'B', 'Nos', '997315', '', '', '', '', '', '', 'Yes', 'No']])[0];
        $this->assertTrue($r['v']['maintain']);
    }

    public function test_existing_and_repeated_names_are_skipped_not_errors(): void
    {
        $r = $this->run_([['Latitude 7490', 'C', 'B'], ['latitude  7490', 'C', 'B'], ['New', 'C', 'B'], ['new', 'C', 'B']], ['latitude 7490' => true]);
        $this->assertSame(['skip', 'skip', 'ok', 'skip'], array_column($r, 'status'));
    }

    public function test_bad_values_are_reported(): void
    {
        $r = $this->run_([['', 'C', 'B'], ['X', 'C', 'B', 'Nos', '12x'], ['Y', 'C', 'B', 'Nos', '', 'abc'], ['Z', 'C', 'B', 'Nos', '', '', '', '', '', '', 'maybe']]);
        $this->assertSame(['err', 'err', 'err', 'err'], array_column($r, 'status'));
    }

    public function test_gst_comes_from_cgst_plus_sgst_or_igst_else_18(): void
    {
        $r = $this->run_([['A', 'C', 'B', 'Nos', '1', '', '', '9', '9'], ['B', 'C', 'B', 'Nos', '1', '', '', '', '', '5'], ['C', 'C', 'B', 'Nos', '1']]);
        $this->assertSame([18.0, 5.0, 18.0], array_map(fn ($x) => $x['v']['gst'], $r));
    }

    public function test_missing_unit_means_nos_and_excel_style_hsn_is_cleaned(): void
    {
        $r = $this->run_([['A', 'C', 'B', '', '997315.0']])[0];
        $this->assertSame('Nos', $r['v']['unit']);
        $this->assertSame('997315', $r['v']['hsn']);
    }
}
