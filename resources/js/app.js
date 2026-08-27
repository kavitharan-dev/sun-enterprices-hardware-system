import './bootstrap';

import Alpine from 'alpinejs';
import { registerSearchableSelect } from './searchable-select';

window.Alpine = Alpine;

registerSearchableSelect(Alpine);

Alpine.start();
