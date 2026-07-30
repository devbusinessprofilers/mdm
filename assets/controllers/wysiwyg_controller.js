import { Controller } from '@hotwired/stimulus';
import { getDefaultLocale } from '@symfony/ux-translator';

export default class extends Controller {
    static targets = ['wysiwyg'];
    static values = { maxHeight: Number, maxLength: Number };

    connect() {
        this.init();
    }

    init() {
        const locale = getDefaultLocale();

        // @see: https://www.tiny.cloud/docs/tinymce/latest/ui-localization/
        const lang = 'fr' === locale ? 'fr-FR' : 'en';

        const options = {
            target: this.wysiwygTarget,
            language: lang,
            menubar: false,
            statusbar: false,
            plugins: ['link', 'lists', 'emoticons', 'wordcount'],
            toolbar: 'undo redo bold italic alignleft aligncenter alignright alignjustify bullist emoticons',
            content_style: 'body { font-size: 0.75rem; margin: 8px; }',
            setup: (editor) => {
                editor.on('keydown', (e) => this.onKeyDown(e, editor));
                editor.on('input', () => this.onChange(editor));
                editor.on('PastePreProcess', (e) => this.onPaste(e, editor));
            },
        }

        if (this.maxHeightValue > 0) {
            options.max_height = this.maxHeightValue;
        }

        tinymce.init(options);
    }

    onChange(editor) {
        editor.save();
    }

    onPaste(e, editor) {
        if (this.maxLengthValue === 0) {
            return;
        }

        const count = editor.plugins.wordcount.body.getCharacterCount();
        const remaining = this.maxLengthValue - count;

        if (remaining <= 0) {
            e.content = '';
            return;
        }

        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = e.content;
        const pastedText = tempDiv.textContent || tempDiv.innerText || '';

        if (pastedText.length > remaining) {
            e.content = pastedText.substring(0, remaining);
        }
    }

    onKeyDown(e, editor) {
        if (this.maxLengthValue === 0) {
            return;
        }

        const count = editor.plugins.wordcount.body.getCharacterCount();
        // Match only printable characters
        if (count >= this.maxLengthValue && e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
        }
    }

    disconnect() {
        const editor = tinymce.get(this.wysiwygTarget.id);
        if (editor) {
            editor.remove();
        }
    }
}
