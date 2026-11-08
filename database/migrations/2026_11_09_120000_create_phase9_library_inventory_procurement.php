<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phase 9C/9D - library copies and fines, inventory and assets, procurement. */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------ library
        Schema::table('library_books', function (Blueprint $table) {
            $table->string('publisher', 150)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 40)->nullable();
            $table->string('shelf_location', 60)->nullable();
        });

        Schema::create('library_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->string('accession_number', 40);
            $table->string('status', 12)->default('available');          // available | on_loan | lost | damaged | withdrawn
            $table->string('condition_note', 190)->nullable();
            $table->date('acquired_on')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'accession_number']);
            $table->index(['library_book_id', 'status']);
        });

        Schema::table('library_loans', function (Blueprint $table) {
            $table->foreignId('library_copy_id')->nullable()->constrained('library_copies')->nullOnDelete();
            $table->unsignedSmallInteger('renewals')->default(0);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('library_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('reason', 12);                                // overdue | lost | damaged
            $table->string('status', 10)->default('unpaid');             // unpaid | paid | waived
            $table->string('note', 300)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        // Existing catalogue rows get one real copy per counted copy so loans and counts keep working.
        foreach (DB::table('library_books')->get() as $book) {
            $out = DB::table('library_loans')->where('library_book_id', $book->id)->where('status', 'out')->count();
            for ($i = 1; $i <= (int) $book->copies_total; $i++) {
                DB::table('library_copies')->insert([
                    'school_id' => $book->school_id, 'library_book_id' => $book->id,
                    'accession_number' => 'B'.$book->id.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'status' => $i <= $out ? 'on_loan' : 'available', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // ------------------------------------------------------------------ suppliers (used by assets and procurement)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        // ------------------------------------------------------------------ inventory and assets
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku', 60);
            $table->string('name', 160);
            $table->string('unit', 20)->default('pcs');
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 300)->nullable();
            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'sku']);
        });

        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inventory_stock_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);                                  // receipt | issue | adjustment | transfer_in | transfer_out
            $table->integer('quantity');                                 // signed
            $table->integer('balance_after');
            $table->string('reason', 300)->nullable();
            $table->string('reference', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'inventory_item_id']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 60);
            $table->string('name', 160);
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->date('acquired_on')->nullable();
            $table->unsignedInteger('cost')->default(0);
            $table->string('condition', 12)->default('good');            // new | good | fair | poor
            $table->string('status', 20)->default('in_store');           // in_use | in_store | under_maintenance | disposed | lost
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('disposed_on')->nullable();
            $table->string('disposal_reason', 300)->nullable();
            $table->string('notes', 300)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'tag']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->date('assigned_on');
            $table->date('returned_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('asset_maintenance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                                  // preventive | repair | inspection
            $table->string('description', 300);
            $table->date('scheduled_on');
            $table->date('completed_on')->nullable();
            $table->unsignedInteger('cost')->default(0);
            $table->string('performed_by', 150)->nullable();
            $table->string('status', 12)->default('scheduled');          // scheduled | done | cancelled
            $table->timestamps();
            $table->index(['school_id', 'status', 'scheduled_on']);
        });

        // ------------------------------------------------------------------ procurement
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('title', 190);
            $table->text('justification')->nullable();
            $table->date('needed_by')->nullable();
            $table->string('status', 12)->default('draft');              // draft | submitted | approved | rejected | cancelled | ordered
            $table->unsignedInteger('estimated_total')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'number']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 250);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('estimated_unit_cost')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');              // draft | issued | partially_received | received | cancelled
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->unsignedInteger('total_amount')->default(0);
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'number']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 250);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost')->default(0);
            $table->unsignedInteger('received_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->date('received_on');
            $table->string('note', 300)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'number']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 80);
            $table->date('invoice_date');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('amount');
            $table->string('status', 12)->default('recorded');           // recorded | approved | paid | cancelled
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'supplier_id', 'invoice_number']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach ([
            'supplier_invoices', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders',
            'purchase_request_items', 'purchase_requests', 'asset_maintenance', 'asset_assignments', 'assets',
            'stock_movements', 'inventory_stock', 'inventory_items', 'inventory_categories', 'inventory_locations', 'suppliers',
            'library_fines',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('library_loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('library_copy_id');
            $table->dropConstrainedForeignId('issued_by');
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('renewals');
        });
        Schema::dropIfExists('library_copies');
        Schema::table('library_books', fn (Blueprint $t) => $t->dropColumn(['publisher', 'publication_year', 'language', 'shelf_location']));
    }
};
