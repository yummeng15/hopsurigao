export type ThemeName =
    | 'theme1'
    | 'theme2'
    | 'theme3'
    | 'theme4'
    | 'theme5'
    | 'theme6'
    | 'theme7'
    | 'theme8';

export type ThemeOption = {
    value: ThemeName;
    label: string;
    swatch: string; // a representative color for the switcher UI
};

export const themes: ThemeOption[] = [
    { value: 'theme1', label: 'default', swatch: '#00FFFF' },
    { value: 'theme2', label: 'theme2', swatch: '#00FFFF' },
    { value: 'theme3', label: 'theme3', swatch: '#00FFFF' },
    { value: 'theme4', label: 'theme4', swatch: '#00FFFF' },
    { value: 'theme5', label: 'theme5', swatch: '#00FFFF' },
    { value: 'theme6', label: 'theme6', swatch: '#00FFFF' },
    { value: 'theme7', label: 'theme7', swatch: '#00FFFF' },
    { value: 'theme8', label: 'theme8', swatch: '#00FFFF' },
];

export const DEFAULT_THEME: ThemeName = 'theme1';
