define(
	[
		'Library/jquery-with-dependencies',
		'emberjs',
		'Shared/HttpRestClient'
	],
	function($, Ember, HttpRestClient) {
		return Ember.View.extend({
			tagName: 'input',
			attributeBindings: ['type'],
			type: 'hidden',
			placeholder: 'Type to search',

			// lazily initialized content – will be an array of select2 datums [{id: '12a9837…', text: 'My Node'}, …]:
			content: [],

			// array of allowed node type names, configurable via editorOptions
			nodeTypes: ['TYPO3.Neos:Document'],

			didInsertElement: function() {
				var that = this;

				var currentQueryTimer = null;
				this.$().select2({
					multiple: true,
					minimumInputLength: 3,
					placeholder: this.get('placeholder'),

					query: function (query) {
						if (currentQueryTimer) {
							window.clearTimeout(currentQueryTimer);
						}
						currentQueryTimer = window.setTimeout(function() {
							currentQueryTimer = null;

							var arguments = {
								searchTerm: query.term,
								workspaceName: $('#neos-document-metadata').attr('data-context-__workspacename'),
								nodeTypes: that.get('nodeTypes')
							};

							HttpRestClient.getResource('neos-service-nodes', null, {data: arguments}).then(function(result) {
								var data = {results: []};
								$(result.resource).find('li').each(function(index, value) {
									data.results.push({
										id: $('.node-identifier', value).text(),
										text: $('.node-label', value).text()
									});
									query.callback(data);
								});
							});
						}, 200);
					}
				});

				this.$().select2('container').find('.neos-select2-input').attr('placeholder', this.get('placeholder'));

				this.$().on('change', function() {
					that.set('content', $(this).select2('data'));
				});
			},

			_updateSelect2: function() {
				if (!this.$()) {
					return;
				}
				this.$().select2('data', this.get('content'));
			},

			// actual value used and expected by the inspector:
			value: function(key, value) {
				var that = this;

				if (value) {
					// Remove all items so they don't appear multiple times.
					// TODO: cache already found items and load multiple node records at once
					that.set('content', []);
					// load names of already selected nodes via the Node REST service:
					$(JSON.parse(value)).each(function(index, nodeIdentifier) {
						var item = Ember.Object.extend({
							id: nodeIdentifier,
							text: 'Loading ...'
						}).create();

						that.get('content').pushObject(item);

						var arguments = {
							workspaceName: $('#neos-document-metadata').attr('data-context-__workspacename')
						}
						HttpRestClient.getResource('neos-service-nodes', nodeIdentifier, {data: arguments}).then(function(result) {
							item.set('text', $('.node-label', result.resource).text());
							that._updateSelect2();
						});

					});
					that._updateSelect2();
				}
				return JSON.stringify(this.get('content').map(function(item){
					return item.id;
				}));
			}.property('content.@each')
		});
	}
);
