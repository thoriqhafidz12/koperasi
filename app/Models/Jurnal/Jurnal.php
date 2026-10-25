<?php

namespace App\Models\Jurnal;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
#[Table('jurnal')]
#[Fillable(['jHeadId', 'jNo', 'jTgl', 'jKeterangan', 'jRekDebetKode', 'jRekDebetNama', 'jDebetNilai', 'jRekKreditKode', 'jRekKreditNama', 'jKreditNilai', 'jSumber', 'jImportBatchId', 'jImportFile', 'jStatus', 'jImportBatchId', 'jImportFile'])]

class Jurnal extends Model
{
    protected $primaryKey = 'jId';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
}
