import { Controller } from '@hotwired/stimulus';
import Dropzone from 'dropzone';
import Sortable from 'sortablejs';
import { trans } from '../translator.js';

export default class extends Controller {
    static targets = ['library', 'tabHeader', 'tabContent', 'dropzone'];

    referenceIndex;

    connect() {
        this.referenceIndex = this.libraryTarget.querySelectorAll('[data-media]').length;

        this.initTabs();
        this.initDropzones();
        this.initMeetingRooms();
        this.initSortable();
    }

    initTabs() {
        const activeClass = this.tabHeaderTarget.dataset.tabActiveClass;
        const inactiveClass = this.tabHeaderTarget.dataset.tabInactiveClass;

        this.tabHeaderTarget.querySelectorAll('[data-tab]').forEach(headerElement => {
            headerElement.addEventListener('click', () => {
                this.tabHeaderTarget.querySelectorAll(['[data-tab]']).forEach(tabElement => {
                    if (tabElement.dataset.tab === headerElement.dataset.tab) {
                        tabElement.classList = activeClass;
                    } else {
                        tabElement.classList = inactiveClass;
                    }
                });

                this.libraryTarget.querySelectorAll(['[data-content]']).forEach(contentElement => {
                    if (contentElement.dataset.content === headerElement.dataset.tab) {
                        contentElement.classList.remove('hidden');
                    } else {
                        contentElement.classList.add('hidden');
                    }
                });

                this.dropzoneTarget.querySelectorAll(['[data-dropzone]']).forEach(dropzoneElement => {
                    if (dropzoneElement.dataset.dropzone === headerElement.dataset.tab) {
                        dropzoneElement.classList.remove('hidden');
                    } else {
                        dropzoneElement.classList.add('hidden');
                    }
                });
            })
        });

        this.tabHeaderTarget.firstElementChild.click();
    }

    initDropzones() {
        this.dropzoneTarget.querySelectorAll('[data-dropzone]').forEach((dropzone) => {
            this.createDropzone(dropzone);
        });
    }

    initMeetingRooms() {
        this.tabContentTarget.querySelectorAll('select[data-meeting-room-trigger]').forEach((select) => {
            if (select.value === select.dataset.meetingRoomTrigger) {
                this.tabContentTarget.querySelector(`[data-media-meeting-room="${select.id}"]`).classList.remove('hidden');
            }
        });
    }

    initSortable() {
        this.libraryTarget.querySelectorAll(['[data-content]']).forEach(contentElement => {
            const sortableElement = document.getElementById(contentElement.dataset.content);
            Sortable.create(sortableElement, {
                animation: 150,
                onEnd: () => this.recalculateRanks(sortableElement),
            });
            this.recalculateRanks(sortableElement);
        })
    }

    createDropzone(dropzone) {
        const identifier = dropzone.dataset.dropzone;
        const previewType = dropzone.dataset.previewType;

        const mediaContent = document.getElementById(identifier);
        const previewTemplate = document.getElementById(`${previewType}-template`);

        // @see: https://docs.dropzone.dev/
        const dropzoneOptions = {
            url: dropzone.dataset.uploadPath,
            method: 'post',
            paramName: 'file',
            parallelUploads: 5,
            createImageThumbnails: false,
            previewTemplate: previewTemplate.innerHTML.trim(),
            maxFilesize: dropzone.dataset.fileMaxSize,
            maxFiles: dropzone.dataset.maxFileCount,
            acceptedFiles: dropzone.dataset.acceptedFiles,
        };

        new Dropzone(`#${identifier}-dropzone`, {
            ...dropzoneOptions,
            accept: function(file, done) {
                console.log('accept...', file, this.options.maxFiles);
                // @todo: check picture size in case of picture (min width, min height)!
                // @todo: check max files count manually to include files already loaded!
                return done();
            },
            // @see: https://github.com/dropzone/dropzone/blob/main/src/options.js#L611
            addedfile: function(file) {
                const previewElement = Dropzone.createElement(this.options.previewTemplate);

                if ('picture' === previewType) {
                    previewElement.querySelector('img[data-dz-thumbnail]').src = URL.createObjectURL(file);
                }

                file.previewElement = previewElement;
                file.previewTemplate = file.previewElement; // Backwards compatibility

                mediaContent.append(previewElement);
            },
            success: (file) => {
                const response = JSON.parse(file.xhr.response);
                const htmlPrototypeElement = mediaContent.dataset.prototypeTemplate
                    .replaceAll('__name__', ++this.referenceIndex)
                    .replaceAll('__unique_id__', response.id)
                    .trim()
                ;

                const template = document.createElement('template');
                template.innerHTML = htmlPrototypeElement;

                template.content.querySelector('input[data-media-id]').value = response.id;

                if ('picture' === previewType) {
                    template.content.querySelector('img').src = URL.createObjectURL(file);
                }
                if ('document' === previewType) {
                    template.content.querySelector('a').innerText = file.name;
                }

                file.previewElement.replaceWith(template.content.firstChild);

                this.recalculateRanks(mediaContent);
            },
            error: (file, message) => {
                console.log('error...', file);
                // @todo: display error message...
                file.previewElement.remove();
            },
        });
    }

    removeMedia(event) {
        const media = document.getElementById(event.params.identifier);
        if (media) {
            media.remove();
        }
    }

    cropMedia(event) {
        const cropModalElement = document.getElementById('crop-modal');
        const cropModalController = this.application.getControllerForElementAndIdentifier(cropModalElement, 'provider-portal--crop-modal');

        cropModalController.open(event.params.identifier);
    }

    recalculateRanks(content) {
        let index = 1;
        content.querySelectorAll('[data-media]').forEach((media) => {
            const rankInput = media.querySelector('input[data-media-rank]');
            if (rankInput) {
                rankInput.value = index;
            }
            const rankDisplay = media.querySelector('div[data-media-rank]');
            if (rankDisplay) {
                rankDisplay.innerText = index === 1
                    ? `${index} ${trans('assets.form.sheet.library.picture.main')}`
                    : index;
            }
            ++index;
        });
    }

    toggleMeetingRoom(event) {
        const select = event.target;
        const triggerValue = select.dataset.meetingRoomTrigger;
        if (!triggerValue) {
            return;
        }

        const meetingRoomTarget = this.tabContentTarget.querySelector(`[data-media-meeting-room="${select.id}"]`)
        if (select.value === triggerValue) {
            meetingRoomTarget.classList.remove('hidden');
        } else {
            meetingRoomTarget.classList.add('hidden');
        }
    }

    disconnect() {
        // @todo: disconnect elements => dropzone.disabled() + sortable.destroy()
    }
}
