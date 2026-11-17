import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';

function showsEditReason(composer) {
  return app.forum.attribute('showDiffEditReason') !== false && (!app.forum.attribute('diffMainPostOnly') || composer.attrs.post.number() === 1);
}

export default function addEditReason() {
  extend('flarum/forum/components/EditPostComposer', 'oninit', function () {
    this.composer.fields.editReason = Stream('');
  });

  extend('flarum/forum/components/EditPostComposer', 'headerItems', function (items) {
    if (!showsEditReason(this)) return;

    const id = `diff-edit-reason-${this.attrs.post.id()}`;

    items.add(
      'editReason',
      <div className="DiffEditReason-form">
        <input
          id={id}
          className="FormControl"
          type="text"
          maxlength={500}
          autocomplete="off"
          aria-label={extractText(app.translator.trans('huseyinfiliz-diff.forum.composer.editReasonLabel'))}
          placeholder={extractText(app.translator.trans('huseyinfiliz-diff.forum.composer.editReasonPlaceholder'))}
          disabled={this.loading || this.attrs.disabled}
          value={this.composer.fields.editReason()}
          oninput={(event) => this.composer.fields.editReason(event.target.value)}
        />
      </div>,
      -100
    );
  });

  extend('flarum/forum/components/EditPostComposer', 'data', function (data) {
    if (showsEditReason(this)) {
      const reason = this.composer.fields.editReason().trim();

      if (reason) data.editReason = reason;
    }
  });
}
