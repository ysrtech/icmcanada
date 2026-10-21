# Font Awesome webfont (vendored)

`fontawesome-webfont.woff2` / `.woff` from Font Awesome 4.7.0, the final 4.x
release - https://github.com/FortAwesome/Font-Awesome

The font is licensed under the SIL OFL 1.1. Only the font files are vendored,
not Font Awesome's CSS: this module uses the glyphs solely through
`content: "\fXXX"` rules on `font-family: FontAwesome` in ../styles.css, so the
@font-face declaration there is all that is needed. Codepoints are unchanged
between 4.x releases, so these render the same glyphs the module has always
asked for.

Previously the stylesheet was pulled from //maxcdn.bootstrapcdn.com, which no
longer serves it - the profile bar buttons rendered as blank boxes. Vendoring
also means the admin does not need outbound network access to draw its icons.
