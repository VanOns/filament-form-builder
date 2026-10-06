export default function mergeTagPickerComponent() {
    return {
        isOpen: false,
        search: '',
        groups: [],
        icons: {},
        active: 0,
        anchor: null,
        insert: null,
        position: null,

        init() {
            // Scrolling inside a modal or slide-over moves the button without scrolling the window.
            this.reposition = () => this.isOpen && this.place()
            document.addEventListener('scroll', this.reposition, true)
            window.addEventListener('resize', this.reposition)
        },

        destroy() {
            document.removeEventListener('scroll', this.reposition, true)
            window.removeEventListener('resize', this.reposition)
        },

        open({ anchor, insert, groups, icons }) {
            if (this.isOpen && this.anchor === anchor) {
                this.close()

                return
            }

            Object.assign(this, { anchor, insert, groups, icons, search: '', active: 0, position: null, isOpen: true })

            this.$nextTick(() => {
                this.place()
                this.$refs.search.focus()
            })
        },

        close() {
            this.isOpen = false
            this.anchor = null
        },

        place() {
            const button = this.anchor.getBoundingClientRect()
            const width = this.$el.offsetWidth
            const height = this.$el.offsetHeight
            const gap = 6
            const fitsBelow = button.bottom + gap + height <= window.innerHeight

            this.position = {
                top: fitsBelow || button.top - gap - height < 0 ? button.bottom + gap : button.top - gap - height,
                left: Math.max(8, Math.min(button.right - width, window.innerWidth - width - 8)),
            }
        },

        get filtered() {
            const query = this.search.trim().toLowerCase()

            return this.groups
                .map((group) => ({
                    ...group,
                    tags: group.tags.filter((tag) => query === '' || tag.label.toLowerCase().includes(query) || tag.id.toLowerCase().includes(query)),
                }))
                .filter((group) => group.tags.length > 0)
        },

        get flat() {
            return this.filtered.flatMap((group) => group.tags)
        },

        isActive(tag) {
            return this.flat[this.active]?.id === tag.id
        },

        move(step) {
            const count = this.flat.length

            if (count === 0) {
                return
            }

            this.active = (this.active + step + count) % count

            this.$nextTick(() => this.$refs.list.querySelector('.ffb-active')?.scrollIntoView({ block: 'nearest' }))
        },

        choose(tag = this.flat[this.active]) {
            if (! tag) {
                return
            }

            this.insert(tag.id)
            this.close()
        },
    }
}
