export default function formCanvasComponent({ key }) {
    return {
        isDragging: false,

        init() {
            const group = `ffb-canvas-${key}`
            // Mouse-driven dragging behaves the same in every browser, unlike native drag and drop.
            const dragOptions = { forceFallback: true, fallbackOnBody: true, animation: 150 }

            this.palette = window.Sortable.create(this.$refs.palette, {
                group: { name: group, pull: 'clone', put: false },
                sort: false,
                draggable: '[data-type]',
                ...dragOptions,
            })

            this.grid = window.Sortable.create(this.$refs.grid, {
                group: { name: group, pull: false, put: true },
                draggable: '[data-item]',
                dataIdAttr: 'data-item',
                filter: 'button',
                preventOnFilter: false,
                ...dragOptions,
                ghostClass: 'ffb-canvas-ghost',
                onStart: () => {
                    this.isDragging = true
                },
                onEnd: (event) => {
                    // The click that ends a drag must not open the edit modal.
                    setTimeout(() => (this.isDragging = false))

                    if (event.oldIndex === event.newIndex) {
                        return
                    }

                    this.$wire.mountAction(
                        'reorder',
                        { items: this.grid.toArray() },
                        { schemaComponent: key },
                    )
                },
                onAdd: (event) => {
                    const type = event.item.dataset.type
                    const position = this.positionOf(event.item)

                    // Livewire renders the new item once the add modal is submitted,
                    // and Sortable still needs the element until its drop handling ends.
                    setTimeout(() => event.item.remove())

                    this.add(type, position)
                },
            })
        },

        destroy() {
            this.palette?.destroy()
            this.grid?.destroy()
        },

        positionOf(element) {
            let position = 0
            let sibling = element.previousElementSibling

            while (sibling) {
                if (sibling.matches('[data-item]')) {
                    position++
                }

                sibling = sibling.previousElementSibling
            }

            return position
        },

        add(type, position = null) {
            this.$wire.mountAction(
                'add',
                { type, position },
                { schemaComponent: key },
            )
        },

        edit(item) {
            if (this.isDragging) {
                return
            }

            this.$wire.mountAction('edit', { item }, { schemaComponent: key })
        },
    }
}
