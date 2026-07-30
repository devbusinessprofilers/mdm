import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['inputFile', 'pictureTemplate', 'avatarWrapper'];
    static values = {initials: String};

    onClick() {
        this.inputFileTarget.click();
    }

    onFileChange = () => {
        const uploadedFiles = this.inputFileTarget.files;

        if (uploadedFiles.length !== 1) {
            return;
        }

        const preview = this.pictureTemplateTarget.cloneNode(true);
        preview.src = URL.createObjectURL(uploadedFiles[0]);
        preview.alt = this.initialsValue;

        this.avatarWrapperTarget.innerHTML = preview.outerHTML;
    }
}
