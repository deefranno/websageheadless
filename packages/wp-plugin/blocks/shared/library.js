import { __ } from '@wordpress/i18n';
import { SelectControl, Spinner } from '@wordpress/components';
import { useEntityRecords } from '@wordpress/core-data';

/** Select one published item from a library post type. */
export function LibrarySelect({ postType, label, value, onChange }) {
  const { records, isResolving } = useEntityRecords('postType', postType, {
    per_page: 100,
    status: 'publish',
    _fields: 'id,title',
  });
  if (isResolving && !records) return <Spinner />;
  const options = [
    { label: __('— Choose —', 'websage-headless'), value: '' },
    ...(records ?? []).map((record) => ({
      label: record.title?.rendered || `#${record.id}`,
      value: String(record.id),
    })),
  ];
  return (
    <SelectControl
      __next40pxDefaultSize
      label={label}
      value={value ? String(value) : ''}
      options={options}
      onChange={(next) => onChange(next ? Number(next) : undefined)}
    />
  );
}
