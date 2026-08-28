/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'
// L'import définit les custom elements <cropper-canvas>, <cropper-image>,
// <cropper-selection>, <cropper-handle>, <cropper-shade>…
import 'cropperjs'

// Modale de recadrage visuel d'une photo. La zone de sélection est verrouillée
// sur le ratio de l'image (après rotation) et ne peut pas descendre sous les
// minima d'upload (dam.image_largeur_min × dam.image_hauteur_min) exprimés en
// pixels réels de l'original. À la validation, les coordonnées converties dans
// l'espace naturel de l'image (après auto-orient + rotation, celui qu'attend
// ImageMagick) sont posées dans les champs cachés du formulaire de métadonnées,
// qui est soumis tel quel (PATCH intercepté par lieu-media#save).
export default class extends Controller {
    static targets = ['scale', 'canvas', 'picture', 'selection', 'error']
    static values = {
        originalUrl: String,
        formName: String,
        minWidth: Number,
        minHeight: Number,
        scaleStep: Number,
        minScale: Number,
        maxScale: Number,
    }

    // Appelé à chaque ouverture (événement modal:opened) : l'original n'est
    // chargé qu'au premier affichage, jamais au préchargement des modales —
    // l'URL présignée derrière l'endpoint /original reste ainsi fraîche.
    async prepare() {
        this.errorTarget.hidden = true
        if (!this.ready) {
            if (this.loading) return
            this.loading = true
            try {
                this.pictureTarget.setAttribute('src', this.originalUrlValue)
                await this.pictureTarget.$ready()
                const image = this.pictureTarget.$image
                this.natural = { width: image.naturalWidth, height: image.naturalHeight }
                this.ready = true
            } catch (_) {
                // La réouverture retentera le chargement (URL présignée fraîche).
                this.errorTarget.hidden = false
                return
            } finally {
                this.loading = false
            }
        }
        await this.syncFromForm()
    }

    // Les champs cachés du formulaire sont la source de vérité : après un
    // Annuler, la réouverture repart de l'état enregistré, pas de l'essai
    // abandonné.
    async syncFromForm() {
        const stored = this.storedState()
        this.rotation = stored.rotation
        await this.applyView(stored.crop)
    }

    rotateLeft() { this.rotate(-90) }
    rotateRight() { this.rotate(90) }

    async rotate(delta) {
        if (!this.ready) return
        this.rotation = (this.rotation + delta + 360) % 360
        // Une sélection convertie n'a plus de sens après rotation (le ratio
        // s'inverse à 90/270) : on repart de la zone maximale.
        await this.applyView(null)
    }

    downScale() { this.shiftScale(-this.scaleStepValue) }
    upScale() { this.shiftScale(this.scaleStepValue) }

    shiftScale(delta) {
        const value = Math.min(this.maxScaleValue, Math.max(this.minScaleValue, parseFloat(this.scaleTarget.value) + delta))
        this.scaleTarget.value = String(value)
        this.updateScale()
    }

    // Le curseur exprime un multiplicateur de l'échelle « contain » de
    // référence, recalculée après chaque rotation. Le zoom passe par le
    // garde-fou limitTransform : si la transformation est refusée (la
    // sélection sortirait de l'image), le curseur se recale sur l'échelle
    // réellement appliquée.
    updateScale() {
        if (!this.ready) return
        const target = this.baseScale * parseFloat(this.scaleTarget.value)
        const [a, b] = this.pictureTarget.$getTransform()
        const current = Math.hypot(a, b)
        if (current > 0 && target > 0) this.pictureTarget.$scale(target / current)
        const [na, nb] = this.pictureTarget.$getTransform()
        this.scaleTarget.value = String(Math.hypot(na, nb) / this.baseScale)
    }

    // Garde-fou sur toute transformation de l'image (molette, déplacement,
    // curseur) : l'échelle reste entre minScale (pas de dézoom sous le cadrage
    // initial) et maxScale, et l'image doit toujours couvrir la sélection.
    limitTransform(event) {
        if (this.transforming || !this.ready) return
        const [a, b, , , e, f] = event.detail.matrix
        const scale = Math.hypot(a, b)
        if (scale < this.baseScale * this.minScaleValue - 0.0001
            || scale > this.baseScale * this.maxScaleValue + 0.0001) {
            event.preventDefault()
            return
        }
        // Boîte englobante de l'image une fois la transformation appliquée :
        // l'origine CSS est le centre de l'élément, seule la translation (e, f)
        // déplace ce centre — rotations multiples de 90°, échelle uniforme.
        const canvasRect = this.canvasTarget.getBoundingClientRect()
        const imgRect = this.pictureTarget.getBoundingClientRect()
        const [, , , , currentE, currentF] = this.pictureTarget.$getTransform()
        const centerX = imgRect.left + imgRect.width / 2 - currentE + e
        const centerY = imgRect.top + imgRect.height / 2 - currentF + f
        const [natW, natH] = this.rotatedNatural()
        const width = natW * scale
        const height = natH * scale
        const selection = this.selectionTarget
        const selLeft = canvasRect.left + selection.x
        const selTop = canvasRect.top + selection.y
        if (selLeft < centerX - width / 2 - 1 || selTop < centerY - height / 2 - 1
            || selLeft + selection.width > centerX + width / 2 + 1
            || selTop + selection.height > centerY + height / 2 + 1) {
            event.preventDefault()
            return
        }
        // Zoom à la molette : recaler le curseur sur l'échelle acceptée.
        this.scaleTarget.value = String(Math.min(this.maxScaleValue, Math.max(this.minScaleValue, scale / this.baseScale)))
    }

    // Garde-fou sur l'événement (annulable) de la sélection : refuse toute
    // zone qui sortirait de l'image ou descendrait sous le plancher, avec une
    // tolérance d'un pixel pour les arrondis.
    limitSelection(event) {
        if (this.applying || !this.ready) return
        const zone = this.toNatural(event.detail)
        const [natW, natH] = this.rotatedNatural()
        const [minW, minH] = this.minZone()
        if (zone.width < minW - 1 || zone.height < minH - 1
            || zone.x < -1 || zone.y < -1
            || zone.x + zone.width > natW + 1 || zone.y + zone.height > natH + 1) {
            event.preventDefault()
        }
    }

    save() {
        if (!this.ready) return
        const form = this.form()
        if (!form) return
        const zone = this.clampedSelection()
        const [natW, natH] = this.rotatedNatural()
        // Une sélection pleine image équivaut à « pas de rognage » : on ne
        // stocke pas un crop qui ne rogne rien.
        const full = 0 === zone.x && 0 === zone.y && zone.width === natW && zone.height === natH
        this.writeFields(form, full ? null : zone, this.rotation)
        form.requestSubmit()
        this.close()
    }

    // Remet la vue à zéro (cadre plein, rotation 0, zoom de base) sans fermer
    // ni enregistrer : le retrait effectif du recadrage passe par « Valider »
    // (sélection pleine image = crop effacé).
    async reset() {
        if (!this.ready) return
        this.rotation = 0
        await this.applyView(null)
    }

    cancel() { this.close() }

    close() {
        this.application.getControllerForElementAndIdentifier(this.element, 'modal')?.close()
    }

    // — Vue —

    // Réapplique rotation + cadrage « contain », puis pose la sélection :
    // celle du crop fourni, sinon la zone maximale (l'image entière).
    async applyView(crop) {
        this.transforming = true
        try {
            this.pictureTarget.$resetTransform()
            if (0 !== this.rotation) this.pictureTarget.$rotate(`${this.rotation}deg`)
            this.pictureTarget.$center('contain')
        } finally {
            this.transforming = false
        }
        await this.nextFrame()
        const [a, b] = this.pictureTarget.$getTransform()
        this.baseScale = Math.hypot(a, b)
        this.scaleTarget.value = '1'
        const [natW, natH] = this.rotatedNatural()
        this.selectionTarget.aspectRatio = natW / natH
        this.setSelection(crop ?? { x: 0, y: 0, width: natW, height: natH })
    }

    setSelection(zone) {
        const { canvasRect, imgRect, scale } = this.geometry()
        this.applying = true
        try {
            this.selectionTarget.$change(
                imgRect.left - canvasRect.left + zone.x / scale,
                imgRect.top - canvasRect.top + zone.y / scale,
                zone.width / scale,
                zone.height / scale,
            )
            this.selectionTarget.hidden = false
        } finally {
            this.applying = false
        }
    }

    // — Conversion de coordonnées —
    // Les rotations sont des multiples de 90° : la bounding box de l'image
    // coïncide exactement avec l'image tournée, aucune matrice n'est requise.

    geometry() {
        const canvasRect = this.canvasTarget.getBoundingClientRect()
        const imgRect = this.pictureTarget.getBoundingClientRect()
        const [natW] = this.rotatedNatural()
        return { canvasRect, imgRect, scale: natW / imgRect.width }
    }

    toNatural(selection) {
        const { canvasRect, imgRect, scale } = this.geometry()
        return {
            x: (canvasRect.left + selection.x - imgRect.left) * scale,
            y: (canvasRect.top + selection.y - imgRect.top) * scale,
            width: selection.width * scale,
            height: selection.height * scale,
        }
    }

    clampedSelection() {
        const zone = this.toNatural(this.selectionTarget)
        const [natW, natH] = this.rotatedNatural()
        const [minW, minH] = this.minZone()
        let width = Math.min(natW, Math.max(Math.ceil(minW), Math.round(zone.width)))
        let height = Math.min(natH, Math.max(Math.ceil(minH), Math.round(zone.height)))
        const x = Math.min(natW - width, Math.max(0, Math.round(zone.x)))
        const y = Math.min(natH - height, Math.max(0, Math.round(zone.y)))
        return { x, y, width, height }
    }

    rotatedNatural() {
        return 0 === this.rotation % 180
            ? [this.natural.width, this.natural.height]
            : [this.natural.height, this.natural.width]
    }

    // Plancher exprimé dans l'espace tourné : le ratio étant verrouillé, la
    // plus petite zone admissible est la fraction de couverture qui satisfait
    // à la fois la largeur et la hauteur minimales (bornée à l'image entière
    // pour les originaux qui, une fois tournés, passent sous les minima — le
    // serveur tranchera).
    minZone() {
        const [natW, natH] = this.rotatedNatural()
        const coverage = Math.min(1, Math.max(this.minWidthValue / natW, this.minHeightValue / natH))
        return [natW * coverage, natH * coverage]
    }

    // — Formulaire —

    form() {
        return document.querySelector(`form[name="${this.formNameValue}"]`)
    }

    field(form, name) {
        return form.querySelector(`[name="${this.formNameValue}[${name}]"]`)
    }

    storedState() {
        const form = this.form()
        if (!form) return { crop: null, rotation: 0 }
        const value = name => this.field(form, name)?.value ?? ''
        const parts = ['crop_x', 'crop_y', 'crop_width', 'crop_height'].map(value)
        const crop = parts.every(part => '' !== part)
            ? { x: Number(parts[0]), y: Number(parts[1]), width: Number(parts[2]), height: Number(parts[3]) }
            : null
        return { crop, rotation: (Number(value('rotation')) || 0) % 360 }
    }

    writeFields(form, crop, rotation) {
        this.field(form, 'crop_x').value = crop ? String(crop.x) : ''
        this.field(form, 'crop_y').value = crop ? String(crop.y) : ''
        this.field(form, 'crop_width').value = crop ? String(crop.width) : ''
        this.field(form, 'crop_height').value = crop ? String(crop.height) : ''
        this.field(form, 'rotation').value = String(rotation)
    }

    nextFrame() {
        return new Promise(resolve => window.requestAnimationFrame(() => resolve()))
    }
}
