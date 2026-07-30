<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            // Nullable untuk menjaga kompatibilitas data lama. Form baru dan
            // perubahan data tetap mewajibkan nilai melalui FormRequest.
            $table->string('jenis_barang')->nullable()->after('kategori');
        });
    }

    public function down(): void
    {
        Schema::table('barang', function (Blueprint $table) {
            $table->dropColumn('jenis_barang');
        });
    }
};
