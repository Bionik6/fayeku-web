<?php

namespace App\Console\Commands;

use App\Services\Shared\MetaTemplateFetcher;
use Illuminate\Console\Command;

class SyncWhatsAppTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:templates:sync';

    protected $description = 'Force le rafraîchissement du cache local des templates WhatsApp depuis Meta Graph API';

    public function handle(MetaTemplateFetcher $fetcher): int
    {
        $fetcher->refresh();
        $templates = $fetcher->allTemplates();

        if ($templates === []) {
            $this->warn('Aucun template approuvé récupéré — vérifie WHATSAPP_BUSINESS_ACCOUNT_ID et WHATSAPP_ACCESS_TOKEN.');

            return self::FAILURE;
        }

        $this->info(sprintf('%d template(s) approuvé(s) mis en cache.', count($templates)));

        foreach ($templates as $tpl) {
            $this->line(sprintf(
                '  · %s  (%s)',
                $tpl['name'] ?? '—',
                $tpl['language'] ?? '—',
            ));
        }

        return self::SUCCESS;
    }
}
