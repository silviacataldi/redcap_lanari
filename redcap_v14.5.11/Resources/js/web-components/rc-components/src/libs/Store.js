
const ACTIONS = Object.freeze({
    GET: 'get',
    SET: 'set',
    DELETE: 'delete',
})

/**
 * Get an object's type
 * @param  {*}      obj The object
 * @return {String}     The type
 */
 function getType (obj) {
	return Object.prototype.toString.call(obj).slice(8, -1).toLowerCase();
}

/**
 * reactive store in vanilla javascript
 * @see https://gomakethings.com/simple-reactive-data-stores-with-vanilla-javascript-and-proxies/
 * @param {Object|Array} data 
 * @param {String} name 
 * @returns 
 */
const store = (data = {}, name = 'store', element = document) => {

    /**
	 * Emit a custom event
	 * @param  {String} type   The event type
	 * @param  {*}      detail Any details to pass along with the event
	 */
	function emit (type, detail) {

		// Create a new event
		let event = new CustomEvent(type, {
			bubbles: true,
			cancelable: true,
			detail: detail
		});

		// Dispatch the event
		return element.dispatchEvent(event);
	}

    /**
	 * Create the Proxy handler object
	 * @param  {String} name The namespace
	 * @param  {Object} data The data object
	 * @return {Object}      The Proxy handler
	 */
	function handler (name, data) {
		return {
			get: function (obj, prop) {
				if (prop === '_isProxy') return true;
				if (['object', 'array'].includes(Object.prototype.toString.call(obj[prop]).slice(8, -1).toLowerCase()) && !obj[prop]._isProxy) {
					obj[prop] = new Proxy(obj[prop], handler(name, data));
				}
                emit(name, {data, action:ACTIONS.GET, prop});
                emit(`${name}:${ACTIONS.GET}`, {data, prop});
				return Reflect.get(obj, prop);
			},
			set: function (obj, prop, value) {
				const oldValue = Reflect.get(obj,prop)
                if (oldValue === value) return true;
				Reflect.set(obj, prop, value);
				emit(name, {data, action:ACTIONS.SET, prop, oldValue, value});
				emit(`${name}:${ACTIONS.SET}`, {data, prop, oldValue, value});
				return true;
			},
			deleteProperty: function (obj, prop) {
				Reflect.deleteProperty(obj, prop);
				emit(name, {data, action:ACTIONS.DELETE, prop});
				emit(`${name}:${ACTIONS.DELETE}`, {data, prop});
				return true;
			}
		};
	}

    data = ['array', 'object'].includes(getType(data)) ? data : {value: data};
    return new Proxy(data, handler(name, data));
}
export { store as default, ACTIONS }