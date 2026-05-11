import React from 'react';
import { createRoot } from 'react-dom/client';
import './styles.css';
import App from './App.jsx';

const rootElement = document.getElementById('root');

function showStartupError(error) {
  const message = error?.message || String(error);
  rootElement.innerHTML = `
    <div style="padding: 32px; font-family: Arial, sans-serif; color: #7a1d1d;">
      <h1>React failed to start</h1>
      <pre style="white-space: pre-wrap;">${message}</pre>
    </div>
  `;
}

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  render() {
    if (this.state.error) {
      return (
        <div style={{ padding: 32, fontFamily: 'Arial, sans-serif', color: '#7a1d1d' }}>
          <h1>React failed to render</h1>
          <pre style={{ whiteSpace: 'pre-wrap' }}>{this.state.error.message}</pre>
        </div>
      );
    }

    return this.props.children;
  }
}

window.addEventListener('error', (event) => showStartupError(event.error || event.message));
window.addEventListener('unhandledrejection', (event) => showStartupError(event.reason));

try {
  createRoot(rootElement).render(
    <React.StrictMode>
      <ErrorBoundary>
        <App />
      </ErrorBoundary>
    </React.StrictMode>
  );
} catch (error) {
  showStartupError(error);
}
