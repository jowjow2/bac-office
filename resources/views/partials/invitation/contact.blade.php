{{--
    The procuring entity's contact details that every Invitation to Bid must
    state (RA 12009 IRR Sec. 50.2(m)); values from config/bac-office.php.
--}}
@php $contact = \App\Support\ProcuringEntity::contact(); @endphp
<dl class="ui-dl">
    <div><dt>Procuring entity</dt><dd>{{ \App\Support\ProcuringEntity::name() }}</dd></div>
    <div><dt>Office</dt><dd>{{ collect([$contact['office'], $contact['address']])->filter()->implode(', ') ?: '—' }}</dd></div>
    @if($contact['person'])
        <div><dt>Contact person</dt><dd>{{ $contact['person'] }}@if($contact['person_position'])<br><span class="ui-optional">{{ $contact['person_position'] }}</span>@endif</dd></div>
    @endif
    @if($contact['phone'])
        <div><dt>Telephone</dt><dd><a class="ui-link" href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></dd></div>
    @endif
    @if($contact['fax'])
        <div><dt>Fax</dt><dd>{{ $contact['fax'] }}</dd></div>
    @endif
    @if($contact['email'])
        <div><dt>E-mail</dt><dd><a class="ui-link" href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></dd></div>
    @endif
    <div><dt>Website</dt><dd><a class="ui-link" href="{{ $contact['website'] }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $contact['website']) }}</a></dd></div>
</dl>
