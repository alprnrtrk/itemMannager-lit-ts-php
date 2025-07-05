import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement, property, query } from 'lit/decorators.js';

import adminLoginStyles from './admin-login.scss?inline';

@customElement('admin-login')
export class AdminLogin extends LitElement {
  static styles = css`
    ${unsafeCSS(adminLoginStyles)}
  `;

  @property({ type: String }) errorMessage: string = '';
  @query('#passwordInput') passwordInput!: HTMLInputElement;

  private _handleSubmit(event: Event) {
    event.preventDefault();
    const password = this.passwordInput.value;
    if (password) {
      this.dispatchEvent(new CustomEvent('login-attempt', {
        detail: { password },
        bubbles: true,
        composed: true
      }));
    } else {
      this.errorMessage = 'Please enter a password.';
    }
  }

  render() {
    return html`
      <div class="login-container">
        <h2>Admin Login</h2>
        <form @submit=${this._handleSubmit}>
          <div class="input-group">
            <label for="passwordInput">Password</label>
            <input
              type="password"
              id="passwordInput"
              .value=${''}
              placeholder="Enter password"
              autocomplete="current-password"
            />
          </div>
          ${this.errorMessage ? html`<p class="error-message">${this.errorMessage}</p>` : ''}
          <button type="submit">Login</button>
        </form>
      </div>
    `;
  }
}