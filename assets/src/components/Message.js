/**
 * @module components/Message.js
 * @name Message
 * @copyright 2024 3Liz
 * @author BOISTEAULT Nicolas
 * @license MPL-2.0
 */

import {html, render} from 'lit-html';

/**
 * @class
 * @name Message
 * @augments HTMLElement
 */
export default class Message extends HTMLElement {
    constructor() {
        super();

        this.html = this.innerHTML;
        this.innerHTML = '';
        this.message = this.getAttribute('message') || '';
        this.type = this.getAttribute('type') || 'info';
        this.close = this.getAttribute('close') || true;
        this.timeout = this.getAttribute('timeout');
    }

    connectedCallback() {
        this._template = () => html`
            <button class="btn btn-small" @click=${() => { lizMap.addMessage(this.message, this.type, this.close, this.timeout)}} >${this.html}</button>
        `;

        render(this._template(), this);
    }
}
