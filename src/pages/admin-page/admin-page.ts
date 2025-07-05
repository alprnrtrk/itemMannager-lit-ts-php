import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement, state, query } from 'lit/decorators.js';
import { ifDefined } from 'lit/directives/if-defined.js';

import adminPageStyles from './admin-page.scss?inline';

interface Item {
  id: number;
  name: string;
  description: string;
  price: number;
  imageUrl?: string;
}

@customElement('admin-page')
export class AdminPage extends LitElement {
  static styles = css`
    ${unsafeCSS(adminPageStyles)}
  `;

  @state() private feedbackMessage: string = '';
  @state() private feedbackType: 'success' | 'error' | '' = '';
  @state() private selectedImagePreviewUrl: string | null = null;
  @state() private items: Item[] = [];
  @state() private isLoadingItems: boolean = false;
  @state() private itemsErrorMessage: string = '';
  @state() private editingItem: Item | null = null;

  @query('#itemForm') itemForm!: HTMLFormElement;
  @query('#itemName') itemNameInput!: HTMLInputElement;
  @query('#itemDescription') itemDescriptionTextarea!: HTMLTextAreaElement;
  @query('#itemPrice') itemPriceInput!: HTMLInputElement;
  @query('#itemImageFile') itemImageFile!: HTMLInputElement;


  connectedCallback() {
    super.connectedCallback();
    this._fetchItems();
  }

  private async _fetchItems() {
    this.isLoadingItems = true;
    this.itemsErrorMessage = '';
    try {
      const response = await fetch('/api/items/get');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      if (Array.isArray(data)) {
        this.items = data;
      } else {
        this.itemsErrorMessage = 'Data format error: Expected an array of items.';
        this.items = [];
      }
    } catch (error) {
      console.error('Error fetching items for admin:', error);
      this.itemsErrorMessage = `Failed to load items: ${(error as Error).message}`;
      this.items = [];
    } finally {
      this.isLoadingItems = false;
    }
  }

  private _handleImageChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = (e) => {
        this.selectedImagePreviewUrl = e.target?.result as string;
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      this.selectedImagePreviewUrl = null;
    }
  }

  private _handleEditItem(item: Item) {
    this.editingItem = item;
    this.itemNameInput.value = item.name;
    this.itemDescriptionTextarea.value = item.description;
    this.itemPriceInput.value = item.price.toString();
    this.selectedImagePreviewUrl = item.imageUrl || null;
    this.itemImageFile.value = '';
    this.feedbackMessage = '';
    this.feedbackType = '';
  }

  // Handle cancelling edit mode
  private _handleCancelEdit() {
    this.editingItem = null;
    this.itemForm.reset();
    this.selectedImagePreviewUrl = null;
    this.feedbackMessage = '';
    this.feedbackType = '';
  }

  private async _handleSubmit(event: Event) {
    event.preventDefault();
    this.feedbackMessage = '';
    this.feedbackType = '';

    const formData = new FormData(this.itemForm);

    const name = this.itemNameInput.value;
    const description = this.itemDescriptionTextarea.value;
    const price = parseFloat(this.itemPriceInput.value);
    const imageFile = this.itemImageFile.files ? this.itemImageFile.files[0] : null;

    if (!name || !description || isNaN(price)) {
      this.feedbackMessage = 'Please fill in all required fields (Name, Description, Price).';
      this.feedbackType = 'error';
      return;
    }

    let apiUrl = '/api/items/add';
    let httpMethod = 'POST';
    let itemId = Date.now().toString();

    if (this.editingItem) {
      apiUrl = '/api/items/update';
      itemId = this.editingItem.id.toString();
      formData.append('id', itemId);

      if (!imageFile && this.editingItem.imageUrl) {
        formData.append('existingImageUrl', this.editingItem.imageUrl);
      }
    } else {
      if (!imageFile) {
        this.feedbackMessage = 'Please select an image file for the new item.';
        this.feedbackType = 'error';
        return;
      }
    }

    if (imageFile) {
      formData.append('itemImage', imageFile);
    }

    formData.set('name', name);
    formData.set('description', description);
    formData.set('price', price.toString());


    try {
      const response = await fetch(apiUrl, {
        method: httpMethod,
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || `Failed to ${this.editingItem ? 'update' : 'add'} item`);
      }

      const result = await response.json();
      this.feedbackMessage = result.message || `Item ${this.editingItem ? 'updated' : 'added'} successfully!`;
      this.feedbackType = 'success';

      this.itemForm.reset();
      this.selectedImagePreviewUrl = null;
      this.editingItem = null;
      this._fetchItems();

    } catch (error) {
      console.error(`Error ${this.editingItem ? 'updating' : 'adding'} item:`, error);
      this.feedbackMessage = `Error: ${(error as Error).message}`;
      this.feedbackType = 'error';
    }
  }

  private async _handleDeleteItem(itemId: number) {
    if (!confirm('Are you sure you want to delete this item?')) {
      return;
    }

    this.feedbackMessage = '';
    this.feedbackType = '';

    try {
      const response = await fetch(`/api/items/delete`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: itemId }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to delete item');
      }

      const result = await response.json();
      this.feedbackMessage = result.message || 'Item deleted successfully!';
      this.feedbackType = 'success';
      this._fetchItems();

    } catch (error) {
      console.error('Error deleting item:', error);
      this.feedbackMessage = `Error: ${(error as Error).message}`;
      this.feedbackType = 'error';
    }
  }

  render() {
    return html`
      <div class="admin-dashboard">
        <h2>Admin Panel</h2>
        <p>Welcome to the admin dashboard. Here you can manage and add items.</p>

        <div class="add-item-section">
          <h3>${this.editingItem ? 'Edit Item' : 'Add New Item'}</h3>
          <form id="itemForm" @submit=${this._handleSubmit}>
            <div class="form-group">
              <label for="itemName">Item Name</label>
              <input type="text" id="itemName" name="name" required .value=${this.editingItem?.name || ''} />
            </div>
            <div class="form-group">
              <label for="itemDescription">Description</label>
              <textarea id="itemDescription" name="description" rows="3" required .value=${this.editingItem?.description || ''}></textarea>
            </div>
            <div class="form-group">
              <label for="itemPrice">Price</label>
              <input type="number" id="itemPrice" name="price" step="0.01" required .value=${this.editingItem?.price.toString() || ''} />
            </div>
            <div class="form-group">
              <label for="itemImageFile">Item Image</label>
              <input type="file" id="itemImageFile" name="itemImage" accept="image/*" @change=${this._handleImageChange} />
              ${(this.selectedImagePreviewUrl || this.editingItem?.imageUrl) ? html`
                <img src=${ifDefined(this.selectedImagePreviewUrl || this.editingItem?.imageUrl)} alt="Image Preview" class="image-preview" />
              ` : ''}
            </div>
            <button type="submit">${this.editingItem ? 'Update Item' : 'Add Item'}</button>
            ${this.editingItem ? html`
              <button type="button" class="cancel-button" @click=${this._handleCancelEdit}>Cancel Edit</button>
            ` : ''}
          </form>

          ${this.feedbackMessage ? html`
            <p class="feedback-message ${this.feedbackType}">${this.feedbackMessage}</p>
          ` : ''}
        </div>

        <div class="manage-items-section">
          <h3>Manage Existing Items</h3>
          ${this.isLoadingItems ? html`<p class="status-message">Loading items...</p>` : ''}
          ${this.itemsErrorMessage ? html`<p class="status-message error">${this.itemsErrorMessage}</p>` : ''}
          ${!this.isLoadingItems && !this.itemsErrorMessage && this.items.length === 0 ? html`<p class="status-message no-items">No items found. Add some above!</p>` : ''}

          ${!this.isLoadingItems && this.items.length > 0 ? html`
            <div class="item-list">
              ${this.items.map(item => html`
                <div class="item-list-card">
                  <img src=${ifDefined(item.imageUrl)} alt=${ifDefined(item.name)} class="item-list-image">
                  <div class="item-list-details">
                    <div class="item-list-name">${item.name}</div>
                    <div class="item-list-price">$${item.price.toFixed(2)}</div>
                  </div>
                  <div class="item-list-actions">
                    <button class="edit-button" @click=${() => this._handleEditItem(item)}>Edit</button>
                    <button class="delete-button" @click=${() => this._handleDeleteItem(item.id)}>Delete</button>
                  </div>
                </div>
              `)}
            </div>
          ` : ''}
        </div>

      </div>
    `;
  }
}