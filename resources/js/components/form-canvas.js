export default function formCanvasComponent({ key, widths, minimums }) {
    return {
        isDragging: false,

        // The field whose settings are open, until its modal closes.
        editing: null,

        // The field being dragged, the canvas as it was when the drag began, the
        // pointer, and the widths the canvas shows for where the field would land.
        drag: null,

        init() {
            const group = `ffb-canvas-${key}`
            // Mouse-driven dragging behaves the same in every browser, unlike native drag and drop.
            const dragOptions = { forceFallback: true, fallbackOnBody: true, animation: 150 }

            this.palettes = [...this.$root.querySelectorAll('[data-palette]')].map((palette) => window.Sortable.create(palette, {
                group: { name: group, pull: 'clone', put: false },
                sort: false,
                draggable: '[data-type]',
                ...dragOptions,
                onStart: (event) => this.startDrag(event.item),
                // Once a new field is on the canvas, place() decides where it goes.
                onMove: (event) => ! (event.to === this.$refs.grid && this.$refs.grid.contains(event.dragged)),
                onEnd: (event) => this.stopDrag(event.item),
            }))

            this.grid = window.Sortable.create(this.$refs.grid, {
                group: { name: group, pull: false, put: true },
                draggable: '[data-item]',
                dataIdAttr: 'data-item',
                filter: 'button',
                preventOnFilter: false,
                ...dragOptions,
                ghostClass: 'ffb-canvas-ghost',
                onStart: (event) => {
                    this.isDragging = true
                    this.startDrag(event.item)
                },
                onMove: () => false,
                onEnd: (event) => {
                    // The click that ends a drag must not open the edit modal.
                    setTimeout(() => (this.isDragging = false))

                    const { order, uuid, width, spans } = this.drag ?? {}
                    const moved = { ...spans, [uuid]: width }

                    this.stopDrag(event.item)

                    const isChanged = this.grid.toArray().join() !== order
                        || Object.entries(moved).some(([item, span]) => span !== this.spanOf(item))

                    if (width && isChanged) {
                        this.$wire.mountAction(
                            'reorder',
                            { items: this.grid.toArray(), spans: moved },
                            { schemaComponent: key },
                        )
                    }
                },
                onChange: (event) => {
                    if (event.item.dataset.type) {
                        this.place()
                    }
                },
                onAdd: (event) => {
                    const type = event.item.dataset.type
                    const position = this.positionOf(event.item)
                    const { width, spans } = this.drag ?? {}

                    // Livewire renders the new item once the add modal is submitted,
                    // and Sortable still needs the element until its drop handling ends.
                    setTimeout(() => event.item.remove())

                    this.add(type, position, width ?? null, { ...spans })
                },
            })
        },

        destroy() {
            this.palettes?.forEach((palette) => palette.destroy())
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

        spanOf(uuid) {
            return Number(this.$refs.grid.querySelector(`[data-item="${CSS.escape(uuid)}"]`)?.dataset.span)
        },

        startDrag(element) {
            this.drag = {
                element,
                uuid: element.dataset.item ?? null,
                // A hidden field shows at full width, so it never shares a row.
                isHidden: element.classList.contains('ffb-canvas-item-hidden'),
                minimum: element.dataset.type ? (minimums[element.dataset.type] ?? 3) : Number(element.dataset.minimum),
                order: this.grid.toArray().join(),
                snapshot: this.takeSnapshot(element),
                pointer: null,
                placed: null,
                width: null,
                spans: {},
            }

            this.onPointerMove = (event) => {
                this.drag.pointer = { x: event.clientX, y: event.clientY }
                this.place()
            }

            document.addEventListener('pointermove', this.onPointerMove)

            // A new field dragged off the canvas again leaves its rows as they were.
            this.observer = new MutationObserver(() => {
                if (this.drag && ! this.$refs.grid.contains(element)) {
                    this.drag.placed = null
                    this.restore()
                }
            })

            this.observer.observe(this.$refs.grid, { childList: true })
        },

        stopDrag(element) {
            document.removeEventListener('pointermove', this.onPointerMove)
            this.observer?.disconnect()
            this.restore()
            this.drag = null

            if (element.dataset.type) {
                element.style.removeProperty('--ffb-span')
                delete element.dataset.width
                delete element.dataset.widthLabel
            }
        },

        // Where everything sat when the drag began, relative to the grid. Placing
        // the field changes the layout under the pointer, so deciding on the live
        // layout would make it jump back and forth. The rows leave out the dragged
        // field, so the row it came from shows the room it leaves.
        takeSnapshot(dragged) {
            const origin = this.$refs.grid.getBoundingClientRect()
            const entries = []

            for (const child of this.$refs.grid.children) {
                if (! child.matches('[data-item]')) {
                    continue
                }

                const rect = child.getBoundingClientRect()

                entries.push({
                    element: child,
                    uuid: child.dataset.item,
                    isDragged: child === dragged,
                    span: Number(child.dataset.span),
                    minimum: Number(child.dataset.minimum),
                    shareable: ! child.classList.contains('ffb-canvas-item-hidden'),
                    left: rect.left - origin.left,
                    right: rect.right - origin.left,
                    top: rect.top - origin.top,
                    bottom: rect.bottom - origin.top,
                })
            }

            const others = entries.filter((entry) => ! entry.isDragged)
            const rows = []
            let column = 12

            for (const entry of entries) {
                if (column + entry.span > 12) {
                    rows.push({ members: [], isOrigin: false, top: entry.top, bottom: entry.bottom })
                    column = 0
                }

                const row = rows.at(-1)

                row.top = Math.min(row.top, entry.top)
                row.bottom = Math.max(row.bottom, entry.bottom)
                column += entry.span

                if (entry.isDragged) {
                    row.isOrigin = true
                } else {
                    row.members.push(entry)
                }
            }

            for (const row of rows) {
                row.start = others.indexOf(row.members[0])
                row.end = row.start + row.members.length
            }

            return { others, rows: rows.filter((row) => row.members.length > 0) }
        },

        // Between rows, or near the top or bottom edge of one, the field gets a
        // row of its own. Within a row it joins it: it takes the room left, or
        // the fields share the row equally as long as each keeps its minimum.
        slotAt(x, y) {
            const { rows, others } = this.drag.snapshot
            const minimum = this.drag.minimum
            const ownRow = (index) => ({ index, width: 12, spans: {}, row: null })

            for (const row of rows) {
                if (y < row.top) {
                    return ownRow(row.start)
                }

                if (y > row.bottom) {
                    continue
                }

                const edge = Math.min(24, Math.max(8, (row.bottom - row.top) * 0.2))

                if (y < row.top + edge) {
                    return ownRow(row.start)
                }

                if (y > row.bottom - edge || this.drag.isHidden) {
                    return ownRow(row.end)
                }

                const index = row.start + row.members.filter((entry) => (entry.left + entry.right) / 2 < x).length
                const fill = this.widestWithin(12 - row.members.reduce((sum, entry) => sum + entry.span, 0), minimum)

                if (fill) {
                    return { index, width: fill, spans: {}, row }
                }

                const width = 12 / (row.members.length + 1)

                if (widths[width] && minimum <= width && row.members.every((entry) => entry.shareable && entry.minimum <= width)) {
                    return { index, width, spans: Object.fromEntries(row.members.map((entry) => [entry.uuid, width])), row }
                }

                return ownRow(row.end)
            }

            return ownRow(others.length)
        },

        // The row a moved field leaves closes up behind it. Mirrors
        // FormCanvas::fillRowWithout().
        closeOrigin(slot) {
            const origin = this.drag.snapshot.rows.find((row) => row.isOrigin)

            if (! origin || slot.row === origin || origin.members.some((entry) => ! entry.shareable)) {
                return {}
            }

            const spans = this.closestSpans(origin.members.map((entry) => entry.span), origin.members.map((entry) => entry.minimum), 12)

            return spans ? Object.fromEntries(origin.members.map((entry, index) => [entry.uuid, spans[index]])) : {}
        },

        // The widths closest to the given spans that fill exactly the room, none
        // below its minimum. Mirrors FormCanvas::closestSpans().
        closestSpans(spans, minimums, room) {
            const options = Object.keys(widths).map(Number)
            let closest = null
            let score = null

            const search = (chosen, left) => {
                if (chosen.length === spans.length) {
                    if (left !== 0) {
                        return
                    }

                    const changes = chosen.map((span, index) => span - spans[index])
                    const candidate = [
                        changes.reduce((sum, change) => sum + Math.abs(change), 0),
                        changes.reduce((sum, change) => sum + change ** 2, 0),
                    ]

                    if (! score || candidate[0] < score[0] || (candidate[0] === score[0] && candidate[1] < score[1])) {
                        closest = chosen
                        score = candidate
                    }

                    return
                }

                for (const option of options) {
                    if (option >= minimums[chosen.length] && option <= left) {
                        search([...chosen, option], left - option)
                    }
                }
            }

            search([], room)

            return closest
        },

        // Mirrors FieldWidth::within(): only the widths the layout offers.
        widestWithin(span, minimum) {
            return Object.keys(widths).map(Number).filter((width) => width >= minimum && width <= span).pop()
        },

        place() {
            const drag = this.drag

            if (! drag?.pointer || ! this.$refs.grid.contains(drag.element)) {
                return
            }

            const origin = this.$refs.grid.getBoundingClientRect()
            const slot = this.slotAt(drag.pointer.x - origin.left, drag.pointer.y - origin.top)
            const spans = { ...this.closeOrigin(slot), ...slot.spans }
            const reference = drag.snapshot.others[slot.index]?.element ?? null
            const placed = JSON.stringify([slot.index, slot.width, spans])

            if (drag.element.nextElementSibling === reference && placed === drag.placed) {
                return
            }

            drag.placed = placed

            if (drag.element.nextElementSibling !== reference) {
                this.$refs.grid.insertBefore(drag.element, reference)
            }

            this.restore()

            drag.element.style.setProperty('--ffb-span', slot.width)

            if (drag.element.dataset.type) {
                drag.element.dataset.width = slot.width
                drag.element.dataset.widthLabel = widths[slot.width]
            }

            for (const [uuid, span] of Object.entries(spans)) {
                this.$refs.grid.querySelector(`[data-item="${CSS.escape(uuid)}"]`)?.style.setProperty('--ffb-span', span)
            }

            drag.width = slot.width
            drag.spans = spans
        },

        restore() {
            for (const item of this.$refs.grid.querySelectorAll('.ffb-canvas-item')) {
                item.style.setProperty('--ffb-span', item.dataset.span)
            }

            if (this.drag) {
                this.drag.spans = {}
            }
        },

        add(type, position = null, width = null, spans = {}) {
            this.$wire.mountAction(
                'add',
                { type, position, width, spans },
                { schemaComponent: key },
            )
        },

        resize(item, width) {
            this.$wire.mountAction('resize', { item, width }, { schemaComponent: key })
        },

        edit(item) {
            if (this.isDragging) {
                return
            }

            this.editing = item
            this.$wire.mountAction('edit', { item }, { schemaComponent: key })
        },
    }
}
