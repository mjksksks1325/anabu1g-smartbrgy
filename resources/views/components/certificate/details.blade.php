@props(['rows', 'keepBlank' => false])
{{--
    The label and value list the templates print ("FULL:", "ADDRESS:" ...): labels at the margin, values in bold capitals
    from 38.1 mm. A row whose third item is true starts a new group after one blank line. Empty optional rows are left out,
    except on historical reprints (keep-blank), which reproduce the rows exactly as they were issued.
--}}
<table class="details" aria-label="Certificate details">
    @foreach ($rows as [$label, $value, $startsGroup])
        @continue(! $keepBlank && blank($value))
        <tr @class(['group-start' => $startsGroup])><th scope="row">{{ $label }}:</th><td>{{ $value }}</td></tr>
    @endforeach
</table>
