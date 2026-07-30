<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use App\Pim\Entity\Fiche;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Fiche> */
final class FicheVoter extends Voter
{
    public const VIEW = 'FICHE_VIEW';
    public const EDIT = 'FICHE_EDIT';
    public const SUBMIT = 'FICHE_SUBMIT';
    public const MANAGE_AFFILIATIONS = 'FICHE_MANAGE_AFFILIATIONS';
    public const VALIDATE = 'FICHE_VALIDATE';
    public const PUBLISH = 'FICHE_PUBLISH';
    public const ARCHIVE = 'FICHE_ARCHIVE';
    public const DELETE = 'FICHE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Fiche && in_array($attribute, [self::VIEW, self::EDIT, self::SUBMIT, self::MANAGE_AFFILIATIONS, self::VALIDATE, self::PUBLISH, self::ARCHIVE, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }
        if ([] !== array_intersect(['ROLE_BP_VALIDATOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], $user->getRoles())) {
            return true;
        }

        return in_array('ROLE_BP_EDITOR', $user->getRoles(), true)
            && in_array($attribute, [self::VIEW, self::EDIT, self::SUBMIT], true);
    }
}
