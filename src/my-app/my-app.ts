import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';
import page from 'page';
import viteLogo from '../assets/vite-logo.png';

import '../pages/home-page/home-page';
import '../pages/admin-page/admin-page';
import '../pages/not-found-page/not-found-page';
import '../pages/admin-login/admin-login';
import '../pages/items-page/items-page';

import myAppStyles from './my-app.scss?inline';

@customElement('my-app')
export class MyApp extends LitElement {
  static styles = css`
    ${unsafeCSS(myAppStyles)}
  `;

  @property({ type: Object }) data: object | null = null;
  @state() currentView: string = 'home-page';
  @state() private isAdminAuthenticated: boolean = false;
  @state() private adminAuthError: string = '';

  constructor() {
    super();
    this._setupRouter();
    this.isAdminAuthenticated = sessionStorage.getItem('adminAuth') === 'true';
  }

  private _setupRouter() {
    page('/', () => {
      this.currentView = 'home-page';
      this.adminAuthError = '';
    });

    page('/items', () => {
      this.currentView = 'items-page';
      this.adminAuthError = '';
    });

    page('/admin', () => {
      this.adminAuthError = '';
      this.currentView = 'admin-page';
    });

    page('*', () => {
      this.currentView = 'not-found-page';
      this.adminAuthError = '';
    });
    page();
  }

  private async _handleAdminLoginAttempt(e: CustomEvent) {
    const password = e.detail.password;
    this.adminAuthError = '';

    const isAuthenticated = await this._authenticateAdmin(password);
    if (isAuthenticated) {
      this.isAdminAuthenticated = true;
      sessionStorage.setItem('adminAuth', 'true');
    } else {
      this.adminAuthError = 'Invalid password. Please try again.';
      this.isAdminAuthenticated = false;
      sessionStorage.removeItem('adminAuth');
    }
  }

  private async _authenticateAdmin(password: string): Promise<boolean> {
    try {
      const response = await fetch('/api/auth/admin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ password: password }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        console.error('Authentication failed:', errorData.message);
        return false;
      }

      const result = await response.json();
      return result.authenticated;

    } catch (error) {
      console.error('Network or server error during authentication:', error);
      return false;
    }
  }

  private async _fetchDataFromPHP() {
    try {
      const response = await fetch('/api/data');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      this.data = await response.json();
    } catch (error) {
      console.error('Error fetching data:', error);
      this.data = { error: (error as Error).message };
    }
  }

  private _navigate(path: string, e: Event) {
    e.preventDefault();
    page.show(path);
  }

  render() {
    let pageContent;
    if (this.currentView === 'admin-page') {
      if (this.isAdminAuthenticated) {
        pageContent = html`<admin-page></admin-page>`;
      } else {
        pageContent = html`<admin-login @login-attempt=${this._handleAdminLoginAttempt} .errorMessage=${this.adminAuthError}></admin-login>`;
      }
    } else if (this.currentView === 'home-page') {
      pageContent = html`<home-page></home-page>`;
    } else if (this.currentView === 'items-page') { // NEW: Render items-page
      pageContent = html`<items-page></items-page>`;
    } else {
      pageContent = html`<not-found-page></not-found-page>`;
    }

    return html`
      <header>
        <img src="${viteLogo}" alt="Vite Logo" class="logo">
        <h1>My Lit + PHP App</h1>
        <nav>
          <a href="/" @click=${(e: Event) => this._navigate('/', e)}>Home</a>
          <a href="/items" @click=${(e: Event) => this._navigate('/items', e)}>Items</a>
          <a href="/admin" @click=${(e: Event) => this._navigate('/admin', e)}>Admin</a>
        </nav>
      </header>

      <main>
        <div class="router-content">
          ${pageContent}
        </div>

        <div class="api-section">
          <button @click=${this._fetchDataFromPHP}>Fetch Data from PHP API</button>
          ${this.data ? html`<pre>${JSON.stringify(this.data, null, 2)}</pre>` : ''}
        </div>
      </main>

      <p class="app-footer">
        Built with Lit and PHP
      </p>
    `;
  }
}