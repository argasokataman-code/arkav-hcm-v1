import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './styles/public-landing-reference.css';
import { PublicLandingReferenceApp } from './components/public-landing-reference-app.jsx';
import { readLandingBootstrapData } from './public/public-landing-contract.js';

const rootNode = document.getElementById('landing-react-root');
const bootstrap = readLandingBootstrapData();

if (rootNode && bootstrap) {
    const root = createRoot(rootNode, { identifierPrefix: 'landing-' });
    root.render(
        <StrictMode>
            <PublicLandingReferenceApp bootstrap={bootstrap} />
        </StrictMode>
    );
}