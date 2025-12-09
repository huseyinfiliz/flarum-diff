import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';
import Page from 'flarum/common/components/Page';
import Post from 'flarum/common/models/Post';
import Model from 'flarum/common/Model';

import Diff from './models/Diff';
import DiffDropdown from './components/DiffDropdown';

app.initializers.add('the-turk-diff', () => {
  app.store.models.diff = Diff;

  Post.prototype.revisionCount = Model.attribute('revisionCount');
  Post.prototype.canViewEditHistory = Model.attribute('canViewEditHistory');
  Post.prototype.canRollbackEditHistory = Model.attribute('canRollbackEditHistory');
  Post.prototype.canDeleteEditHistory = Model.attribute('canDeleteEditHistory');

  extend(CommentPost.prototype, 'headerItems', function (items) {
    const post = this.attrs.post;

    // Replace "edited" text with "edited" button
    if (post.isEdited() && !post.isHidden() && post.canViewEditHistory() && post.revisionCount() > 0) {
      // Remove existing edited item if present
      if (items.has('edited')) {
        items.remove('edited');
      }

      // Add our DiffDropdown
      items.add('edited', <DiffDropdown post={post} />);
    }

    // Remove diffs cache when post is editing
    if (this.isEditing() && app.cache.diffs && app.cache.diffs[this.attrs.post.id()]) {
      delete app.cache.diffs[this.attrs.post.id()];
    }
  });

  // Prevent dropdown from closing when user clicks on deleted diff
  extend(Page.prototype, 'oninit', function () {
    $('body').on('click', 'li.ParentDiff.DeletedDiff, li.SubDiff', function (e) {
      e.stopPropagation();
    });
  });
});