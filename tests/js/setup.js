// tests/js/setup.js — registers happy-dom globals before each test file
import { GlobalRegistrator } from "@happy-dom/global-registrator";
GlobalRegistrator.register();
// Set test token so api.js header injection is predictable
window.__crxToken = 'test-token-for-tests';
