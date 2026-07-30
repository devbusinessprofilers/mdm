<?php

namespace App\Pim\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class FloatSubmit
{
    public string $label;

    public bool $disabled = false;

    /**
     * Identifier of form to submit.
     * Sample:
     *     <twig:Form:FloatSubmit formId="{{ form.vars.id }}" .../>.
     *
     * @see mount method
     */
    public ?string $formId = null;

    /**
     * Custom identifier for dynamic form (due to stimulus controller to bind submit button)
     * Sample:
     *     <twig:DynamicForm:SampleForm data-dynamic-form-id="xxx" .../>
     *     <twig:Form:FloatSubmit dynamicFormId="xxx" .../>.
     *
     * @see mount method
     * @see stimulus float_submit_controller.js
     */
    public ?string $dynamicFormId = null;

    public function mount(?string $formId = null, ?string $dynamicFormId = null): void
    {
        if (
            (null === $formId && null === $dynamicFormId)
            || (null !== $formId && null !== $dynamicFormId)
        ) {
            throw new \LogicException('You must set either formId OR dynamicFormId');
        }

        $this->formId = $formId;
        $this->dynamicFormId = $dynamicFormId;
    }
}
