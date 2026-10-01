export default class DiffListState {
  constructor(post, forModal, moreResults, selectedItem) {
    this.post = post;
    this.forModal = forModal;
    this.moreResults = moreResults || false;
    this.selectedItem = selectedItem;
    this.loading = false;

    if (!app.cache.diffs) {
      app.cache.diffs = {};
    }
  }

  /**
   * Load revisions.
   *
   * @public
   */
  load() {
    // don't do anything if we already cached revisions for the post.
    // lazy-loading will perform loadMore() if there are moreResults
    if (app.cache.diffs[this.post.id()]) return this.redrawList();

    this.loadMore();
  }

  /**
   * Load the next page of revision results.
   *
   * @public
   */
  loadMore() {
    const cachedPages = app.cache.diffs[this.post.id()] || [];
    const totalLoaded = cachedPages.reduce((acc, p) => acc + p.length, 0);

    // don't do anything if we already cached ALL revisions for the post.
    if (totalLoaded > 0 && totalLoaded >= this.post.revisionCount() + 1) {
      return;
    }

    this.loading = true;
    this.redrawList();

    // set URL parameters
    const params = {
      filter: { post_id: this.post.id() },
    };

    if (totalLoaded > 0) {
      params.page = {
        offset: totalLoaded,
      };
    }

    return app.store
      .find('diff', params)
      .then(this.parseResults.bind(this))
      .catch(() => {})
      .then(() => {
        this.loading = false;
        this.redrawList();
      });
  }

  /**
   * Parse results and append them to the revision list.
   *
   * @param {Diff[]} results
   * @return {Diff[]}
   */
  parseResults(results) {
    app.cache.diffs[this.post.id()] = app.cache.diffs[this.post.id()] || [];

    if (results.length) app.cache.diffs[this.post.id()].push(results);

    this.moreResults = !!(results.payload?.links?.next);

    return results;
  }

  /**
   * Redraw the list based on parent component.
   */
  redrawList() {
    m.redraw();
  }
}
