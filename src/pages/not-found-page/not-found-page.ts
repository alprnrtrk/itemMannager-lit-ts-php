import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement } from 'lit/decorators.js';

import myAppStyles from './not-found-page.scss?inline';


@customElement('not-found-page')
export class NotFoundPage extends LitElement {
  static styles = css`
    ${unsafeCSS(myAppStyles)}
  `;

  render() {
    return html`
      <h2>404 - Page Not Found</h2>
      <p>The page you are looking for does not exist.</p>
      <p>Please check the URL or go back to the <a href="/">Home page</a>.</p>
    `;
  }
}