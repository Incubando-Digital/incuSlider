# incuSlider

Banner slider reutilizable que reemplaza el patrón "N sliders duplicados con visibility conditions" por **UN solo Elementor Loop Carousel** con filtrado server-side por contexto del user (rank, país, rol, fechas).

**Estado**: v1.1.0 — primer release como plugin standalone.

## Features

- ✅ CPT `incu_slide` con admin completo
- ✅ Axes API extensible via filter PHP (`incuslider_register_axes`)
- ✅ Built-in axes: MyCred Rank, Country (ISO-2 desde user_meta), WP Role
- ✅ Metabox con **media picker** nativo, **link picker** (WP wpLink), **Select2** multi-select, **datepicker** jQuery UI
- ✅ Columnas custom en listado: thumbnail, targeting con tags coloreados, vigencia, orden
- ✅ Bulk actions: duplicar slides
- ✅ Drag & drop sort por `menu_order`
- ✅ **Vista previa por contexto**: simulá qué slides verá un user con rank/país/rol específicos sin cambiar de cuenta
- ✅ **Onboarding wizard** con detección automática de dependencias
- ✅ WP-CLI `wp incuslider migrate --from-page=<id>` para extraer sliders de Elementor data existente
- ✅ Compatible con Elementor Pro Loop Carousel / Loop Grid via Custom Query ID `incuslider_main`

## Instalación

1. Subir la carpeta a `wp-content/plugins/incuslider/`
2. Activar desde **Plugins → Plugins instalados**
3. Seguir el wizard de setup que aparece en el admin

## Uso editorial (sin código)

### Crear una slide

1. **incuSlider → Add New** en sidebar
2. Título: nombre interno de referencia
3. **Imagen destacada** (sidebar): imagen desktop
4. **Metabox incuSlider — Configuración**:
   - **Imagen Mobile**: click "Seleccionar imagen" → abre Media Library
   - **URL del Link**: pegá URL externa o usá "Buscar contenido" para enlazar a páginas internas
   - **Heading / Subheading**: opcionales
5. **Visibilidad por contexto**: por cada axis (rank, país, rol):
   - Marcá "Mostrar a todos" si la slide es global, O
   - Seleccioná uno o varios valores específicos (Select2 con búsqueda)
6. **Vigencia** (opcional): rango de fechas
7. Publicar

### Crear el Loop Item template (Theme Builder)

El Loop Item define cómo se ve UNA slide. Diseñalo en **Templates → Theme Builder → Loop Item**:

- **Imagen de la slide**: usá la **imagen destacada** del CPT. Para mostrarla como fondo:
  - En un Container, Background → Classic → Image → click el ícono dinámico (🔧) → **"Imagen destacada"** (dynamic tag `post-featured-image`).
  - ⚠️ Importante: es **"Imagen destacada"** (`post-featured-image`), NO confundir con otros tags de imagen.
- **Texto/heading**: widget Heading → dynamic tag **"Título de la entrada"** (`post-title`) o un campo custom del slide.
- **Link**: el campo `_incu_link_url` del slide (custom field dynamic tag, o ACF si lo usás).

### Insertar en una página Elementor

1. Agregá widget **Loop Carousel** (Elementor Pro)
2. Configuración:
   - **Source**: Custom
   - **Custom Type**: `incu_slide`
   - **Custom Query ID**: `incuslider_main`
   - **Choose a template**: el Loop Item template del paso anterior
3. Solo se renderizan las slides que matchean al contexto del user actual

### Vista previa por contexto

**incuSlider → Vista previa** en el sidebar admin. Elegí valores de rank/país/rol y ves exactamente qué slides aparecerían sin tener que cambiar de cuenta.

## Extender con custom axes

```php
add_filter('incuslider_register_axes', function($axes) {
    $axes['member_type'] = array(
        'id'      => 'member_type',
        'label'   => 'BuddyBoss Member Type',
        'options' => function() {
            return array(
                'free' => 'Free',
                'premium' => 'Premium',
            );
        },
        'resolve' => function($user_id) {
            return bp_get_member_type($user_id);
        },
    );
    return $axes;
});
```

El metabox detecta automáticamente el nuevo axis y agrega su sección.

## Lógica de matching

Para que un slide se muestre a un user, **TODOS los axes** deben matchear:
- El slide tiene `['all']` en ese axis (sin restricción), O
- El value actual del user para ese axis está incluido en los values del slide

Adicionalmente:
- `_incu_date_from` (si existe) debe ser ≤ ahora
- `_incu_date_to` (si existe) debe ser ≥ ahora

## WP-CLI

```bash
# Migrar sliders de una página existente → CPT entries
wp incuslider migrate --from-page=<post_id> --dry-run
wp incuslider migrate --from-page=<post_id> --apply
```

Detecta automáticamente:
- Pares desktop/mobile por sufijo de filename: `-D/-M`, `-dk/-MB`, `-desktop/-mobile`, `_D/_M`
- Visibility conditions del container padre (mycred_rank, PAIS)

## Hooks

| Hook | Tipo | Descripción |
|---|---|---|
| `incuslider_register_axes` | filter | Registrar/modificar axes disponibles |
| `elementor/query/incuslider_main` | action | Lo dispara Elementor cuando renderiza el Loop Carousel con Query ID `incuslider_main` |

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- Elementor Pro 3.8+ (para Loop Carousel / Loop Grid)

## Roadmap

- **v1.2**: Dynamic tags de Elementor (`{{slide-link}}`, `{{slide-heading}}`)
- **v1.3**: Built-in axes BuddyBoss member_type + LearnDash group enrollment
- **v2.0**: Tracking de clicks + A/B testing

## License

GPL-2.0+
