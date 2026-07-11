export class AppDateFormatter {
    static friendlyDate(value?: string | null) {
        if (!value) {
            return 'No date set';
        }

        const [year, month, day] = value.slice(0, 10).split('-').map(Number);

        if (!year || !month || !day) {
            return value;
        }

        return new Intl.DateTimeFormat(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(new Date(year, month - 1, day));
    }
}
