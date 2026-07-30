<?php

namespace App\Pim\Model\ProviderPortal\Mock\Sheet\Activity;

class PurposeChoices
{
    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'Fédérer la cohésion et la dynamique d’équipe' => 'federer-la-cohesion-et-la-dynamique-d-equipe',
            'Communiquer & Collaborer' => 'communiquer-collaborer',
            'Motiver & Engager' => 'motiver-engager',
            'Développer et encourager la culture d’entreprise' => 'developper-et-encourager-la-culture-d-entreprise',
            'Sensibiliser et RSE' => 'sensibiliser-et-rse',
            'Fidéliser & Récompenser' => 'fideliser-recompenser',
            'Gérer les relations & les tensions' => 'gerer-les-relations-les-tensions',
            'Stimuler & Challenger' => 'stimuler-challenger',
        ];
    }
}
