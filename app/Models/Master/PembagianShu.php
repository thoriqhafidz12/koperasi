<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shuKode', 'shuNama', 'shuPersen', 'shuObjekKode', 'shuUrut', 'shuIsActive', 'shuCreatedBy', 'shuUpdatedBy', 'shuDeletedBy'])]
#[Table('ms_shu')]

class PembagianShu extends Model
{
    protected $primaryKey = 'shuId';
    public const CREATED_AT = 'shuCreatedAt';
    public const CREATED_BY = 'shuCreatedBy';
    public const UPDATED_AT = 'shuUpdatedAt';
    public const UPDATED_BY = 'shuUpdatedBy';
    public const DELETED_AT = 'shuDeletedAt';
    public const DELETED_BY = 'shuDeletedBy';
}
