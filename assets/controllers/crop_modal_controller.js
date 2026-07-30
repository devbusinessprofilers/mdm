import { Controller } from '@hotwired/stimulus';
import 'cropperjs';

/**
 * Crop modal is used to crop an image from a media element.
 * This modal can be opened with identifier of the media element.
 * This media element must contain both elements:
 * - "<img...>" with data attribute "data-media-image" (used to get URL of picture to crop)
 * - "<input...>" with data attribute "data-media-crop" (used to set matrix of crop when saving)
 */
export default class extends Controller {
    static targets = ['modal', 'canvas', 'picture', 'selection', 'scale'];
    static values = {
        scaleStep: Number,
        minScale: Number,
        maxScale: Number,
    };

    currentMedia;
    currentScale;

    connect() {
        // NOTE: hack to disable zoom on wheel for canvas!
        document.addEventListener('wheel', (event) => {
            if (event.target.closest('cropper-canvas')) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
            }
        }, { passive: false, capture: true });

        this.currentScale = +this.scaleTarget.value;

        // Recenter image if it moves outside the crop frame
        this._onActionEnd = () => this.constrainImageToSelection();
        this.canvasTarget.addEventListener('actionend', this._onActionEnd);
    }

    disconnect() {
        this.canvasTarget.removeEventListener('actionend', this._onActionEnd);
    }

    constrainImageToSelection() {
        const imageRect = this.pictureTarget.getBoundingClientRect();
        const selectionRect = this.selectionTarget.getBoundingClientRect();

        let dx = 0;
        let dy = 0;

        if (imageRect.left > selectionRect.left) {
            dx = selectionRect.left - imageRect.left;
        } else if (imageRect.right < selectionRect.right) {
            dx = selectionRect.right - imageRect.right;
        }

        if (imageRect.top > selectionRect.top) {
            dy = selectionRect.top - imageRect.top;
        } else if (imageRect.bottom < selectionRect.bottom) {
            dy = selectionRect.bottom - imageRect.bottom;
        }

        if (dx !== 0 || dy !== 0) {
            this.pictureTarget.$move(dx, dy);
        }
    }

    open(mediaIdentifier) {
        const mediaElement = document.getElementById(mediaIdentifier);
        if (!mediaElement) {
            return;
        }

        this.currentMedia = mediaElement;

        const pictureUrl = this.currentMedia.querySelector('[data-media-image]').src;
        const encodedCropData = this.currentMedia.querySelector('[data-media-crop]').value;

        this.pictureTarget.src = pictureUrl;
        this.reset();

        // Display modal now to apply hack below (offset width/height can only be got on displayed element)
        this.modalTarget.classList.toggle('hidden');

        // HACK: we need to set selection size manually since Cropper is hidden per default (modal)
        // => size of web component is not calculated on initialization...
        this.selectionTarget.width = this.canvasTarget.offsetWidth;
        this.selectionTarget.height = this.canvasTarget.offsetHeight;
        this.selectionTarget.style.width = this.canvasTarget.offsetWidth;
        this.selectionTarget.style.height = this.canvasTarget.offsetHeight;

        if (encodedCropData) {
            // HACK: need to wait cropper ready before setting matrix (due to new picture URL...)
            setTimeout(() => {
                const cropData = JSON.parse(encodedCropData);
                this.pictureTarget.$setTransform(cropData.matrix);
                this.scaleTarget.value = +cropData.zoom;
                this.currentScale = +cropData.zoom;
            }, 100);
        }
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget) {
            this.cancel();
        }
    }

    cancel() {
        this.modalTarget.classList.toggle('hidden');
        this.reset();
        this.currentMedia = null;
    }

    save() {
        this.currentMedia.querySelector('[data-media-crop]').value = this.getCrop();
        this.modalTarget.classList.toggle('hidden');
        this.reset();
        this.currentMedia = null;
    }

    reset(soft = false) {
        if (!soft) {
            this.pictureTarget.$resetTransform();
        }

        // code below allows to fit picture with ratio (~ reset crop for new orientation)
        this.pictureTarget.initialCenterSize = 'contain';
        this.pictureTarget.initialCenterSize = 'cover';
        this.scaleTarget.value = 0;
        this.currentScale = 0;
    }

    getCrop() {
        const cropData = {
            matrix: this.pictureTarget.$getTransform(),
            selection: {
                x: this.selectionTarget.x,
                y: this.selectionTarget.y,
                width: this.selectionTarget.style.width,
                height: this.selectionTarget.style.height,
            },
            naturalWidth: this.pictureTarget.$image.naturalWidth,
            naturalHeight: this.pictureTarget.$image.naturalHeight,
            zoom: this.currentScale,
        };

        return JSON.stringify(cropData);
    }

    downScale() {
        this.scale(-this.scaleStepValue);
    }

    upScale() {
        this.scale(+this.scaleStepValue);
    }

    updateScale() {
        const newScale = +this.scaleTarget.value;
        if (this.currentScale === newScale) {
            return;
        }

        const stepCount = Math.round((newScale - this.currentScale) / +this.scaleStepValue);
        const increment = +this.scaleStepValue * Math.sign(stepCount);

        for (let i = 0; i < Math.abs(stepCount); i++) {
            this.pictureTarget.$zoom(increment);
        }

        this.currentScale = newScale;
        this.constrainImageToSelection();
    }

    scale(value) {
        const newScale = (+this.scaleTarget.value) + value;
        if (newScale < this.minScaleValue || newScale > this.maxScaleValue) {
            return;
        }

        this.currentScale = newScale;
        this.scaleTarget.value = newScale;
        this.pictureTarget.$zoom(value);
        this.constrainImageToSelection();
    }

    rotateLeft() {
        this.rotate('-90deg');
    }

    rotateRight() {
        this.rotate('+90deg');
    }

    rotate(value) {
        this.pictureTarget.$rotate(value);
        this.reset(true);
    }
}
