# BurgerPage Refactoring Documentation

## Overview
This document describes the refactoring performed on `BurgerPage.vue` to improve code organization, maintainability, and user experience.

## What Was Changed

### 1. **Type System** (`/resources/js/types/menu.ts`)
- Extracted all TypeScript types into a dedicated file
- Created proper interfaces for `MenuItem`, `OrderItem`, `MenuGroup`, etc.
- Added `ApiResponse` generic type for consistent API handling

### 2. **Constants** (`/resources/js/constants/index.ts`)
- Extracted magic numbers:
  - `TAX_RATE = 0.08`
  - `DEFAULT_AUDIO_VOLUME = 0.9`
- Created `SIZE_ORDER` mapping for consistent sorting
- Defined `API_ENDPOINTS` object for all API routes

### 3. **Composables** (`/resources/js/composables/useOrderApi.ts`)
- Created `useOrderApi` composable for all API interactions
- Centralized error handling
- Exposed reactive `loading` and `error` states
- Implemented three main functions:
  - `loadMenu()` - Fetches menu items
  - `loadOrder()` - Fetches current order
  - `sendTextCommand()` - Sends voice commands

### 4. **Utility Functions**
- **`/resources/js/utils/formatting.ts`**
  - `formatPrice()` - Currency formatting
  - `generateLineKey()` - Stable keys for v-for loops

- **`/resources/js/utils/menuHelpers.ts`**
  - `groupMenuItems()` - Groups menu items by name with variants
  - Eliminates code duplication between sides and drinks

### 5. **Reusable Components**

#### **OrderSummary.vue** (`/resources/js/components/OrderSummary.vue`)
- Displays order items with quantities, modifications, and pricing
- Calculates subtotal, tax, and total
- Shows empty state with helpful instructions
- Fully reactive and type-safe

#### **MenuBurgerSection.vue** (`/resources/js/components/MenuBurgerSection.vue`)
- Displays burger menu items with toppings
- Reusable for any single-variant menu section
- Clean, consistent styling

#### **MenuGroupSection.vue** (`/resources/js/components/MenuGroupSection.vue`)
- Displays grouped menu items (sides/drinks) with size variants
- Supports multiple variants per item
- Configurable header styling

#### **Toast.vue** (`/resources/js/components/Toast.vue`)
- User feedback notification system
- Three types: error, success, info
- Auto-dismisses after 5 seconds
- Accessible with ARIA labels
- Animated slide-in effect

### 6. **Refactored BurgerPage.vue**

#### Improvements:
1. **Better Error Handling**
   - Try-catch blocks around all API calls
   - User-friendly toast notifications
   - Proper error state management

2. **Loading States**
   - Visual loading indicator
   - Disabled buttons during operations
   - Better UX during data fetching

3. **Code Organization**
   - Separated concerns (UI, logic, data)
   - Clear function grouping with comments
   - Reduced from ~400 to ~180 lines

4. **Type Safety**
   - Explicit TypeScript types throughout
   - No `any` types in production code
   - Better IDE autocomplete

5. **Accessibility**
   - ARIA labels on buttons
   - Proper button states (disabled)
   - Screen reader friendly

6. **Performance**
   - Parallel data loading (`Promise.all`)
   - Removed redundant computed properties
   - Proper cleanup in `onBeforeUnmount`

## File Structure

```
resources/js/
├── components/
│   ├── AudioPlayer.vue (existing)
│   ├── RecordButton.vue (existing)
│   ├── OrderSummary.vue (NEW)
│   ├── MenuBurgerSection.vue (NEW)
│   ├── MenuGroupSection.vue (NEW)
│   └── Toast.vue (NEW)
├── composables/
│   └── useOrderApi.ts (NEW)
├── constants/
│   └── index.ts (NEW)
├── pages/
│   ├── BurgerPage.vue (REFACTORED)
│   └── BurgerPage.vue.backup (BACKUP)
├── types/
│   └── menu.ts (NEW)
└── utils/
    ├── formatting.ts (NEW)
    └── menuHelpers.ts (NEW)
```

## Benefits

### Maintainability
- **Single Responsibility**: Each file has one clear purpose
- **DRY Principle**: No code duplication (e.g., grouping logic)
- **Easy Testing**: Composables and utilities are testable in isolation

### Developer Experience
- **Type Safety**: Full TypeScript support with proper types
- **Reusability**: Components can be used in other pages
- **Clarity**: Clear separation between UI and business logic

### User Experience
- **Feedback**: Toast notifications for all actions
- **Loading States**: Visual feedback during operations
- **Error Recovery**: Graceful error handling with clear messages
- **Accessibility**: Better screen reader support

## Migration Notes

### Breaking Changes
None - the API interface remains the same

### Testing Checklist
- [ ] Order loading on page mount
- [ ] Menu display (burgers, sides, drinks)
- [ ] Voice recording and order updates
- [ ] Clear order functionality
- [ ] Audio playback
- [ ] Toast notifications
- [ ] Error handling for network failures
- [ ] Loading states

## Future Improvements

1. **State Management**
   - Consider Pinia store if order logic grows
   - Persist order state across page refreshes

2. **Component Enhancements**
   - Add confirmation dialog before clearing order
   - Show transcript in real-time
   - Add order history

3. **Performance**
   - Implement virtual scrolling for large menus
   - Add service worker for offline support
   - Cache menu data

4. **Testing**
   - Add unit tests for composables
   - Add component tests with Vitest
   - Add E2E tests with Playwright

## Rollback Instructions

If issues arise, restore the original:

```bash
cp resources/js/pages/BurgerPage.vue.backup resources/js/pages/BurgerPage.vue
```

Then remove the new files:
- `resources/js/types/menu.ts`
- `resources/js/constants/index.ts`
- `resources/js/composables/useOrderApi.ts`
- `resources/js/utils/formatting.ts`
- `resources/js/utils/menuHelpers.ts`
- `resources/js/components/OrderSummary.vue`
- `resources/js/components/MenuBurgerSection.vue`
- `resources/js/components/MenuGroupSection.vue`
- `resources/js/components/Toast.vue`
