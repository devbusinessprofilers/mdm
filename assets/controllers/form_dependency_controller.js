import { Controller } from '@hotwired/stimulus';

/**
 * Allows to update one or more elements depending on another one using Symfony form validation.
 * NOTE: when using dependencies among a form, this form must include the current stimulus controller attribute.
 *
 * @see src/Twig/ProviderPortal/FormDependencyExtension.php
 * , {'input_attributes': }
 * Sample :
 *  {{ form_start(form, {'attr': getFormDependencyAttributes()}) }}
 *  <div id="{{ form.vars.id }}">
 *      {% set dependencyIdentifier = 'dependenciesWrapper' %}
 *      {{ form_row(form.addMoreInfos, {'attr': getFormDependencyTriggerAttributes(dependencyIdentifier)}) }}
 *      <div getFormDependencyWrapperAttributes(dependencyIdentifier)|renderHtmlAttributes>
 *          {% if form.additionalInfos is defined %}
 *              {{ form_row(form.additionalInfos) }}
 *          {% endif %}
 *      </div>
 *  </div>
 *  {{ form_end(form) }}
 */
export default class extends Controller {
    static targets = ['form'];

    refreshData(event) {
        const dependencyIdentifier = event.params.wrapperIdentifier ?? null;

        if (!dependencyIdentifier) {
            return;
        }

        const formData = new FormData(this.formTarget);

        // NOTE: require 'http_method_override' to true in Symfony framework config
        formData.append('_method', 'PATCH');

        // NOTE: fix to send data to FormType when uncheck (required to include 'false' in 'false_values' option for CheckboxType)
        // @see https://symfony.com/doc/current/reference/forms/types/checkbox.html#false-values
        if ('checkbox' === event.target.type && !event.target.checked) {
            formData.append(event.target.name, 'false');
        }

        fetch(this.formTarget.action, {
            credentials: 'same-origin',
            method: 'POST',
            body: formData,
        })
            .then(response => response.text())
            .then(html => {
                const wrappers = document.querySelectorAll(`[data-dependency-target="${dependencyIdentifier}"]`);
                const resultElements = document.createRange().createContextualFragment(html).querySelectorAll(`[data-dependency-target="${dependencyIdentifier}"]`);

                [...wrappers].forEach((wrapper, key) => {
                    const element = resultElements[key];
                    if (!!element) {
                        wrapper.innerHTML = element.innerHTML;
                    }
                });
            })
            .catch(error => console.error(error))
        ;
    };

    /**
     * CustomEvent 'provider-portal--form-dependency:change' expected with 'detail' property containing the following properties:
     * - 'params': an object containing the following properties:
     *      - 'wrapperIdentifier': the identifier of the wrapper to update
     * - 'target': the element that triggered the event with the following properties:
     *      - 'name': the name of the element
     *      - 'type': the type of the element
     *      - 'value': the value of the element
     *
     * This custom event can be dispatched by any element of the current form that needs to update the wrapper (i.e. force refresh manually).
     * This can be useful when refresh depends on multiple elements (avoid dispatching multiple times the same event).
     *
     * E.g.: This custom event is used in stimulus controller "address" when the position is updated.
     * The position depends on 2 fields, and we want to refresh the wrapper for computed data latitude and longitude.
     */
    onDataChange(event) {
        this.refreshData(event.detail);
    }
}
