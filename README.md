# HM User Notes

A lightweight WordPress plugin that allows logged-in users to add private comments (notes) to posts that only they can see.

## Features

- **Private Comments**: Comments marked as `hm_user_note` type are only visible to their author
- **Block Variations**: Provides variations for core comment blocks:
  - `User Note Form`: A comment form variation for adding/editing private notes
  - `User Notes List`: A comments list variation that only shows the current user's private notes
- **Block Binding**: Dynamic text binding that shows "Add a note" or "Edit note" based on user's note status
- **Auto-Save**: Notes are automatically saved as you type with a 1-second debounce
- **Clean UI**: Submit button is hidden, form title and profile links are removed
- **One Note Per User**: Each user can only add one private note per post
- **Auto-Population**: The form automatically populates with existing note content
- **Visual Feedback**: Shows "Saving...", "Saved", or "Error saving" indicators
- **Permission Checks**: Only the comment author can view and edit their private notes
- **Server-Side Integration**: Comment ID field and existing content are injected server-side
- **Filterable**: Both "Add a note" and "Edit note" text can be customized via filters

## Installation

1. Install dependencies:
   ```bash
   npm install
   ```

2. Build the assets:
   ```bash
   npm run build
   ```

3. Activate the plugin in WordPress

## Usage

### Adding User Notes to a Post

1. Edit a post or page in the block editor
2. Add the "User Note Form" block variation (search for "User Note Form")
3. Add the "User Notes List" block variation to display existing notes
4. Publish the post

### Frontend Usage

- Logged-in users will see the note form
- If they have an existing note, it will be pre-populated in the form
- Notes auto-save as the user types (1 second debounce)
- Only the user's own private notes will be visible in the notes list

### Block Binding Usage

The plugin provides a block binding source called `hm-user-notes/note-status` that can be used to show dynamic text based on whether the user has a note:

1. Add a paragraph, heading, or button block
2. In the block settings, go to the "Advanced" panel
3. Under "Attributes", bind the content to `hm-user-notes/note-status`
4. The text will automatically show:
   - "Add a note" if the user doesn't have a note
   - "Edit note" if the user has a note

You can also use the post meta field directly:
- Meta key: `hm_user_note_status`

### Customizing Text

You can customize the text shown via filters:

```php
// Customize "Add a note" text
add_filter( 'hm_user_notes_add_text', function( $text, $post_id ) {
    return 'Create your note';
}, 10, 2 );

// Customize "Edit note" text
add_filter( 'hm_user_notes_edit_text', function( $text, $post_id, $user_note ) {
    return 'Update your note';
}, 10, 3 );
```

## Development

```bash
# Start development build with watch mode
npm start

# Build for production
npm run build

# Format JavaScript files
npm run format

# Lint JavaScript files
npm run lint:js
```

## Technical Details

- **Comment Type**: Uses WordPress native comment type system with type `hm_user_note`
- **Build System**: Uses `@wordpress/scripts` for build configuration
- **Block API**: Leverages WordPress block variations and block bindings API
- **REST API**: Uses WordPress REST API for creating/updating comments via AJAX
- **Post Meta**: Virtual post meta field `hm_user_note_status` (dynamically generated via filter)
- **Block Binding**: Custom binding source `hm-user-notes/note-status` for dynamic content

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Node.js for building assets
