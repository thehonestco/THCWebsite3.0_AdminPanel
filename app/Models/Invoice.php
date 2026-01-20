<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'type',
        'lead_id',
        'bank_detail_id',

        'sub_total',
        'cgst',
        'sgst',
        'grand_total',

        'status',
        'description',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'invoice_date' => 'date',

        'sub_total'    => 'decimal:2',
        'cgst'         => 'decimal:2',
        'sgst'         => 'decimal:2',
        'grand_total'  => 'decimal:2',
    ];

    /* =====================================================
     | Relationships
     |=====================================================*/

    /**
     * Invoice belongs to a Lead (Client)
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Invoice uses one Bank Detail
     */
    public function bankDetail()
    {
        return $this->belongsTo(BankDetail::class);
    }

    /**
     * Invoice has many invoice items
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /* =====================================================
     | Helpers / Scopes (Optional but useful)
     |=====================================================*/

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope for pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }
}
