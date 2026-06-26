<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankPackageGrade extends Model
{
    protected $fillable = [
        'question_bank_package_id',
        'grade_id',
        'stream_id',
    ];

    public function package()
    {
        return $this->belongsTo(QuestionBankPackage::class, 'question_bank_package_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }
}