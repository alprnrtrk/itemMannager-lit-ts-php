import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement, state } from 'lit/decorators.js';

import itemsPageStyles from './items-page.scss?inline';

interface Item {
  id: number;
  name: string;
  description: string;
  price: number;
  imageUrl?: string;
}

@customElement('items-page')
export class ItemsPage extends LitElement {
  static styles = css`
    ${unsafeCSS(itemsPageStyles)}
  `;

  @state() private items: Item[] = [];
  @state() private isLoading: boolean = true;
  @state() private errorMessage: string = '';

  connectedCallback() {
    super.connectedCallback();
    this._fetchItems();
  }

  private async _fetchItems() {
    this.isLoading = true;
    this.errorMessage = '';
    try {
      const response = await fetch('/api/items/get');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      if (Array.isArray(data)) {
        this.items = data;
      } else {
        this.errorMessage = 'Data format error: Expected an array of items.';
        this.items = [];
      }
    } catch (error) {
      console.error('Error fetching items:', error);
      this.errorMessage = `Failed to load items: ${(error as Error).message}`;
      this.items = [];
    } finally {
      this.isLoading = false;
    }
  }

  render() {
    if (this.isLoading) {
      return html`<p class="status-message">Loading items...</p>`;
    }
    if (this.errorMessage) {
      return html`<p class="status-message error">${this.errorMessage}</p>`;
    }
    if (this.items.length === 0) {
      return html`<p class="status-message no-items">No items found. Add some from the Admin panel!</p>`;
    }

    return html`
      <div class="items-container">
        <h2>Available Items</h2>
        <div class="item-grid">
          ${this.items.map(item => html`
            <div class="item-card">
              ${item.imageUrl ? html`<img src="${item.imageUrl}" alt="${item.name}" class="item-image" />` : html`<div class="item-no-image">No Image</div>`}
              <div class="item-details">
                <h3 class="item-name">${item.name}</h3>
                <p class="item-description">${item.description}</p>
                <p class="item-price">$${item.price.toFixed(2)}</p>
              </div>
            </div>
          `)}
        </div>
      </div>
    `;
  }
}