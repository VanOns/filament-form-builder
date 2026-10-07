const isAddress = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)

export default function recipientsInputComponent({ state, isMultiple, fields }) {
    return {
        state,
        query: '',
        isOpen: false,
        active: 0,
        isInvalid: false,

        get values() {
            if (isMultiple) {
                return this.state ?? []
            }

            return this.state ? [this.state] : []
        },

        get choices() {
            const query = this.query.trim()
            const search = query.toLowerCase()
            const choices = fields.filter(
                (field) => ! this.values.includes(field.value) && (search === '' || field.label.toLowerCase().includes(search)),
            )

            return isAddress(query) && ! this.values.includes(query) ? [{ value: query, label: query, isAddress: true }, ...choices] : choices
        },

        get fieldChoices() {
            return this.choices.filter((choice) => ! choice.isAddress)
        },

        get addressChoice() {
            return this.choices.find((choice) => choice.isAddress) ?? null
        },

        // Read from the root on every render: a change to x-data would make Livewire start the component over.
        label(value) {
            return fields.find((field) => field.value === value)?.label ?? JSON.parse(this.$root.dataset.labels)[value] ?? value
        },

        isField(value) {
            return value.startsWith('field:')
        },

        // A field that is no e-mail field (any more) mails nobody.
        isBroken(value) {
            return this.isField(value) && ! fields.some((field) => field.value === value)
        },

        isActive(choice) {
            return this.choices[this.active]?.value === choice.value
        },

        open() {
            this.isOpen = true
            this.active = 0
        },

        close() {
            this.isOpen = false
        },

        add(value) {
            this.state = isMultiple ? [...this.values.filter((existing) => existing !== value), value] : value
            this.query = ''
            this.active = 0
            this.isInvalid = false

            if (! isMultiple) {
                this.close()
            }
        },

        remove(value) {
            this.state = isMultiple ? this.values.filter((existing) => existing !== value) : null
        },

        // Pasted lists arrive in one go, so every address in the text is added and only the rest stays behind.
        addTyped() {
            const parts = this.query.split(/[\s,;]+/).filter(Boolean)
            const rest = parts.filter((part) => ! isAddress(part))

            parts.filter(isAddress).forEach((part) => this.add(part))

            this.query = rest.join(' ')
            this.isInvalid = rest.length > 0
        },

        choose(choice = this.choices[this.active]) {
            if (this.isOpen && choice) {
                this.add(choice.value)

                return
            }

            this.addTyped()
        },

        split(event) {
            if (this.query.trim() === '') {
                return
            }

            event.preventDefault()
            this.addTyped()
        },

        removeLast() {
            if (this.query === '' && this.values.length > 0) {
                this.remove(this.values.at(-1))
            }
        },

        leave(event) {
            if (this.$el.contains(event.relatedTarget)) {
                return
            }

            this.addTyped()
            this.close()
        },

        move(step) {
            const count = this.choices.length

            if (! this.isOpen) {
                this.open()

                return
            }

            if (count > 0) {
                this.active = (this.active + step + count) % count
            }
        },
    }
}
