<?php

namespace App\Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    public const TYPES = [
        'subscription-contract' => 'Contrato de Assinatura SaaS',
        'terms-of-use' => 'Termos de Uso',
        'privacy-notice' => 'Aviso de Privacidade',
        'data-processing' => 'Anexo de Tratamento de Dados',
        'support-and-backup' => 'Política de Suporte, Backup e Disponibilidade',
    ];

    public const STATUSES = ['draft', 'published', 'retired'];

    protected $table = 'saas_legal_documents';

    protected $fillable = [
        'type', 'title', 'version', 'status', 'body', 'content_hash',
        'effective_at', 'reviewed_at', 'reviewed_by_name', 'reviewed_by_user_id',
        'published_at', 'published_by_user_id',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where(fn (Builder $published) => $published
                ->whereNull('effective_at')
                ->orWhere('effective_at', '<=', now()));
    }

    public static function hashBody(string $body): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", trim($body)));
    }

    public function hasUnresolvedPublicationMarkers(): bool
    {
        $body = mb_strtoupper($this->body);

        foreach (['MINUTA', 'SEM VIGÊNCIA', '[PREENCHER', 'A DEFINIR'] as $marker) {
            if (str_contains($body, $marker)) {
                return true;
            }
        }

        return false;
    }
}
