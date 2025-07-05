import { LitElement, html, css, unsafeCSS } from 'lit';
import { customElement } from 'lit/decorators.js';

import myAppStyles from './home-page.scss?inline';

@customElement('home-page')
export class HomePage extends LitElement {
  static styles = css`
    ${unsafeCSS(myAppStyles)}
  `;

  render() {
    return html`
      <h2>Welcome Home!</h2>
      <p>This is the main content of your home page. Enjoy exploring!</p>
    `;
  }
}