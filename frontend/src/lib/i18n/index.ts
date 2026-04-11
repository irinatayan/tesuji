import { addMessages, register, init, getLocaleFromNavigator } from 'svelte-i18n';
import en from './en.json';

addMessages('en', en);
register('uk', () => import('./uk.json'));
register('ru', () => import('./ru.json'));

const stored = localStorage.getItem('locale');
const fallback = 'en';

init({
  fallbackLocale: fallback,
  initialLocale: stored ?? getLocaleFromNavigator() ?? fallback,
});
