export default class EventEmitter {
    constructor() {
        this.events = {}
    }

    on(event, callback) {
        if (!this.events[event]) {
            this.events[event] = []
        }
        this.events[event].push(callback)
    }

    off(event, callback) {
        if (!this.events[event]) {
            return
        }
        this.events[event] = this.events[event].filter((l) => l !== callback)
    }

    emit(event, ...args) {
        if (this.events[event]) {
            this.events[event].forEach((callback) => callback(...args))
        }
    }
}
