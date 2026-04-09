import json

with open('excel_rates_parsed.json', 'r') as f:
    data = json.load(f)

for provider, prov_data in data['providers'].items():
    countries = list(prov_data['countries'].keys())
    print(f'{provider}: {len(countries)} countries')
    print(f'  Countries: {countries[:10]}')
    
    if provider == 'Singapore-DHL' and 'Australia' in countries:
        print(f'\n  Australia S-DHL sample:')
        au_data = prov_data['countries']['Australia']
        print(f'    Document 0.5kg: {au_data["document"]["0.5"]}')
        print(f'    Document 1.0kg: {au_data["document"]["1.0"]}')
        print(f'    Document add 0.5kg: {au_data["document"]["add_0.5"]}')
        print(f'    Parcel 0.5kg: {au_data["parcel"]["0.5"]}')
        print(f'    Parcel 1.0kg: {au_data["parcel"]["1.0"]}')
        print(f'    Per 0.5kg (10+kg): {au_data["per_0_5_kg"]}')
        break
