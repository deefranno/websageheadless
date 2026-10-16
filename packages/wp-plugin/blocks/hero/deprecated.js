// Mirrors HeroMigration.php. The PHP migration covers content that is never
// reopened; this one upgrades blocks when an editor opens an old post.
const v0 = {
  attributes: {
    schemaVersion: { type: 'integer' },
    title: { type: 'string', default: '' },
  },
  isEligible: (attributes) => attributes.schemaVersion === 0,
  migrate: ({ title, ...rest }) => ({
    ...rest,
    schemaVersion: 1,
    heading: rest.heading ?? title ?? '',
  }),
  save: () => null,
};

const deprecated = [v0];

export default deprecated;
