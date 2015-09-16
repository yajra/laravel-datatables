<table>
@foreach($data as $row)
    <tr>
        @foreach($row as $key => $value)
            <td>{{ $value }}</td>
        @endforeach
    </tr>
@endforeach
</table>
