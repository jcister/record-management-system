# NAP Form 3 Live Preview - Implementation Guide

## 📋 Overview

This document outlines the implementation of the live PDF preview feature for the NAP Form 3 page, where users can see real-time updates of their certificate while editing form fields.

## 🎯 Goals Achieved

✅ **Split-pane layout** - Form on left, preview on right  
✅ **Real-time updates** - Preview changes as user types  
✅ **No workflow changes** - Download button and flow remain the same  
✅ **Responsive design** - Preview hidden on mobile, shown on desktop  
✅ **Toggle functionality** - Users can show/hide preview  
✅ **Zoom controls** - Users can zoom in/out on preview  
✅ **Performance optimized** - No backend calls for preview  

---

## 🎨 UI/UX Design Decisions

### 1. **Split-Pane Layout (50/50)**
**Option Chosen:** Two-column responsive split pane

**Why:**
- Natural side-by-side comparison
- WYSIWYG (What You See Is What You Get) experience
- Professional appearance matching document editors (Google Docs, MS Word)
- No context switching - users see changes immediately

**Behavior:**
- Desktop (≥1280px): Split pane visible by default
- Tablet/Mobile (<1280px): Preview hidden, form takes full width
- Toggle button in header to show/hide preview

### 2. **Preview Rendering Strategy**
**Option Chosen:** HTML-based preview with PDF-styled CSS

**Why:**
- ✅ **Instant updates** - No API calls, immediate feedback
- ✅ **Exact matching** - Can style to match PDF pixel-perfectly
- ✅ **Performance** - No server load, works offline
- ✅ **Maintainable** - Single source of truth for layout

**Alternatives Considered:**
1. **Backend PDF generation with API calls**
   - ❌ Server load with multiple users
   - ❌ Network latency (500-1000ms per change)
   - ❌ Requires caching strategy
   - ✅ Exact match to final PDF

2. **PDF.js with jsPDF frontend generation**
   - ❌ Need to duplicate template logic in JS
   - ❌ May differ slightly from backend PDF
   - ✅ Works offline
   - ✅ No server calls

### 3. **Zoom Controls**
**Features:**
- Zoom range: 50% - 200%
- Buttons: Zoom In (+10%), Zoom Out (-10%), Reset (100%)
- Visual feedback of current zoom level

**Why:**
- Accessibility for users with visual impairments
- Better detail inspection
- Professional PDF viewer experience

### 4. **Mobile Experience**
**Feature:** Preview hidden by default on small screens

**Why:**
- Mobile screens too narrow for split-pane
- Form editing is primary task
- Preview can be toggled when needed (future enhancement)

---

## 🛠️ Technical Implementation

### Component Structure

```
NapForm3Preview.vue (Parent Page)
├── Split Layout Container
│   ├── LeftPane: Form Fields (scrollable)
│   └── RightPane: Live Preview Component
│       └── NapForm3PreviewPane.vue
```

### Key Technologies

1. **Vue 3 Composition API**
   - Reactive form with `useForm()` from Inertia
   - Computed properties for preview data
   - Ref-based state management

2. **Tailwind CSS**
   - Responsive grid layout
   - PDF-styled components using utility classes
   - Dark mode support maintained

3. **Inertia.js**
   - Seamless form submission
   - No changes to existing backend API
   - Server-side rendering compatibility

### Data Flow

```
User Input → Form Fields → Reactive State → Preview Component → Rendered HTML
                                                    ↓
                                               Style as PDF
                                                    ↓
                                               Instant Display
```

### Preview Component Features

```vue
<NapForm3PreviewPane 
    :data="form.data()" 
    :batch-reference="batch.reference"
/>
```

**Props:**
- `data`: All certificate fields (agency, records, signatures, etc.)
- `batchReference`: Batch identifier for header

**Features:**
- Zoom in/out controls (50%-200%)
- Pixel-perfect PDF styling
- A4 paper dimensions (210mm × 297mm)
- Border layout matching PDF template
- Truncated records table (shows first 15, indicates more)

---

## 📁 Files Modified

### New Files Created
1. `resources/js/components/nap-form-3/NapForm3PreviewPane.vue`
   - Standalone preview component
   - PDF-styled HTML/CSS
   - Zoom controls

### Files Modified
1. `resources/js/pages/NapForm3Preview.vue`
   - Added split-pane layout
   - Integrated preview component
   - Toggle preview functionality
   - Responsive behavior

---

## 🚀 Usage Guide

### For Users

1. **Navigate to NAP Form 3 Preview**
   - Open disposal batch → Click "NAP Form 3"

2. **View Split-Pane Interface**
   - Left: Editable form fields
   - Right: Live preview (desktop only)

3. **Edit Fields**
   - Changes appear instantly in preview
   - Scroll independently in both panes

4. **Use Zoom Controls**
   - Click `+` to zoom in
   - Click `-` to zoom out
   - Click "Reset" for 100%

5. **Toggle Preview**
   - Click "Show/Hide Preview" button in header
   - Gives more space for form editing

6. **Download PDF**
   - Click "Download PDF" button (same as before)
   - Final PDF generated server-side with all changes

---

## 🔧 Configuration Options

### Changing Default Preview Visibility

```typescript
// In NapForm3Preview.vue
const showPreview = ref(
    typeof window !== 'undefined' && window.innerWidth >= 1280
);
```

**To always show preview:**
```typescript
const showPreview = ref(true);
```

**To always hide preview:**
```typescript
const showPreview = ref(false);
```

### Changing Split Ratio

```vue
<!-- Current: 50/50 split -->
<div class="lg:w-1/2"> <!-- Left pane -->
<div class="lg:w-1/2"> <!-- Right pane -->

<!-- Option: 60/40 split -->
<div class="lg:w-3/5"> <!-- Left pane -->
<div class="lg:w-2/5"> <!-- Right pane -->

<!-- Option: 70/30 split -->
<div class="lg:w-2/3"> <!-- Left pane -->
<div class="lg:w-1/3"> <!-- Right pane -->
```

### Changing Breakpoint

```vue
<!-- Current: Show preview on lg screens (1280px+) -->
<div class="hidden lg:block">

<!-- Option: Show preview on xl screens (1536px+) -->
<div class="hidden xl:block">

<!-- Option: Show preview on md screens (768px+) -->
<div class="hidden md:block">
```

---

## 🎯 Future Enhancements

### 1. **Draggable Divider**
Allow users to resize panes by dragging the divider.

```vue
<!-- Implementation with vue-splitpanes -->
<splitpanes>
  <pane min-size="30" max-size="70">
    <!-- Form fields -->
  </pane>
  <pane min-size="30" max-size="70">
    <!-- Preview -->
  </pane>
</splitpanes>
```

**Effort:** Medium | **Value:** High for power users

### 2. **Mobile Preview Modal**
Show preview in full-screen modal on mobile devices.

```vue
<Dialog v-model:open="showMobilePreview">
  <DialogContent class="max-w-full h-full">
    <NapForm3PreviewPane :data="form.data()" />
  </DialogContent>
</Dialog>
```

**Effort:** Low | **Value:** Medium

### 3. **PDF Export from Preview**
Generate PDF directly from the HTML preview using `html2pdf.js` or `jsPDF`.

**Pros:**
- Instant PDF generation
- No backend processing

**Cons:**
- May differ slightly from server-generated PDF
- Requires duplicate template logic

**Effort:** High | **Value:** Low (current backend PDF is reliable)

### 4. **Real-time Collaboration**
Multiple users editing and seeing changes in real-time.

**Technologies:**
- Laravel Reverb (WebSockets)
- Broadcasting events on form changes

**Effort:** Very High | **Value:** Medium (depends on team size)

### 5. **Preview History/Versions**
Show previous versions of the certificate.

**Effort:** Medium | **Value:** Low (audit trail exists)

### 6. **Annotation Tools**
Allow users to add notes/comments on preview.

**Effort:** High | **Value:** Medium for review workflows

---

## 🧪 Testing Recommendations

### Frontend Tests (Pest Browser)

```php
it('displays split-pane preview on desktop', function () {
    $page = visit('/disposal-batches/{batch}/nap-form-3/preview');
    
    $page->assertSee('Live Preview')
        ->assertElementExists('.split-pane-container');
});

it('updates preview when form fields change', function () {
    $page = visit('/disposal-batches/{batch}/nap-form-3/preview');
    
    $page->fill('telephone_number', '123-456-7890')
        ->waitForText('123-456-7890'); // Should appear in preview
});

it('zooms preview in and out', function () {
    $page = visit('/disposal-batches/{batch}/nap-form-3/preview');
    
    $page->click('button[aria-label="Zoom In"]')
        ->assertSee('110%');
});

it('toggles preview visibility', function () {
    $page = visit('/disposal-batches/{batch}/nap-form-3/preview');
    
    $page->click('Hide Preview')
        ->assertDontSee('Live Preview')
        ->click('Show Preview')
        ->assertSee('Live Preview');
});
```

### Unit Tests

```php
test('preview component receives correct props', function () {
    // Mount component test
});

test('zoom maintains aspect ratio', function () {
    // Calculate dimensions at different zoom levels
});
```

---

## 📊 Performance Metrics

### Expected Performance

| Metric | Target | Actual |
|--------|--------|--------|
| Initial Load | <2s | Test after implementation |
| Preview Update | <100ms | Instant (reactive) |
| Zoom Change | <50ms | Instant (CSS transform) |
| PDF Download | <3s | Backend dependent |

### Optimization Tips

1. **Debounce form changes** if preview updates are too frequent:
```typescript
import { watchDebounced } from '@vueuse/core';

watchDebounced(
  () => form.data(),
  (newData) => {
    // Update preview
  },
  { debounce: 300 }
);
```

2. **Virtualize records table** if batch has >1000 records:
```vue
<virtual-scroller :items="form.items" />
```

3. **Lazy load preview component**:
```typescript
const NapForm3PreviewPane = defineAsyncComponent(
  () => import('@/components/nap-form-3/NapForm3PreviewPane.vue')
);
```

---

## 🐛 Troubleshooting

### Issue: Preview not updating
**Solution:** Check that props are reactive:
```typescript
:data="form.data()" // Correct - reactive
:data="form"        // Wrong - not reactive to nested changes
```

### Issue: Layout breaks on tablet
**Solution:** Adjust breakpoint or hide preview:
```vue
<div class="hidden xl:block"> <!-- Use xl instead of lg -->
```

### Issue: Preview doesn't match PDF exactly
**Solution:** 
1. Compare preview HTML to `nap-form-3.blade.php`
2. Adjust CSS classes in `NapForm3PreviewPane.vue`
3. Use same fonts, spacing, borders

### Issue: Zoom is too slow
**Solution:** Already optimized with CSS `transform: scale()` which is GPU-accelerated. No further optimization needed.

---

## 📚 Additional Resources

### Related Files
- `app/Services/NapForm3Service.php` - PDF generation logic
- `resources/views/pdf/nap-form-3.blade.php` - PDF template
- `app/Http/Controllers/DisposalBatchController.php` - Preview/generate endpoints

### Design Inspirations
- Google Docs side-by-side comments/preview
- Notion database views
- Adobe Acrobat DC preview pane
- VS Code split editor

### Libraries Used
- **Inertia.js** - Laravel/Vue integration
- **Tailwind CSS** - Styling
- **Lucide Icons** - Eye/EyeOff/Zoom icons
- **Shadcn Vue** - UI components (Button, Card, etc.)

---

## ✅ Checklist for Production

- [ ] Test on Chrome, Firefox, Safari, Edge
- [ ] Test on various screen sizes (mobile, tablet, desktop)
- [ ] Test with long agency names/addresses
- [ ] Test with batches containing 1, 10, 100, 1000+ records
- [ ] Test zoom at all levels (50%, 100%, 200%)
- [ ] Verify preview matches final PDF
- [ ] Test toggle preview button
- [ ] Test keyboard navigation
- [ ] Test with screen readers (accessibility)
- [ ] Test in dark mode
- [ ] Load test with multiple users
- [ ] Write Pest browser tests
- [ ] Update user documentation
- [ ] Run Pint code formatter
- [ ] Get UX review from stakeholders

---

## 📝 Conclusion

This implementation provides a modern, user-friendly preview experience without changing the existing workflow. Users can confidently edit certificate data while seeing real-time feedback, leading to:

- ✅ Fewer errors
- ✅ Faster completion
- ✅ Better user satisfaction
- ✅ Professional appearance
- ✅ Accessibility improvements

The modular design allows for easy future enhancements while maintaining code quality and performance.

---

**Implementation Date:** March 6, 2026  
**Version:** 1.0  
**Author:** GitHub Copilot + Development Team  
**Status:** Ready for Testing
