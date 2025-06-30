<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaselinkerJournalCheckpoint extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'baselinker_journal_checkpoints';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'last_log_id',
        'processed_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     * We only use processed_at which is handled by the database.
     *
     * @var bool
     */
    public $timestamps = false;
}
