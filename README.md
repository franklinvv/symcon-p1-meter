# P1 Meter for IP-Symcon

An IP-Symcon module for reading data from P1 smart electricity meters. P1 meters are commonly used in the Netherlands and Belgium for residential energy monitoring.

## Features

- Real-time power consumption monitoring
- Electricity consumption tracking (high/low tariff)
- Gas consumption tracking
- Optional power generation tracking (for solar panels)
- Automatic data logging with proper aggregation
- Validates P1 telegrams before processing

## Requirements

- IP-Symcon 8.1 or higher
- A P1 smart meter with serial output
- Serial interface (e.g., USB-to-serial adapter) connected to IP-Symcon

## Installation

Add the following URL in the IP-Symcon Module Control:
```
https://github.com/franklinvv/symcon-p1-meter
```

## Configuration

1. Create a new instance under *Splitter Instances* > *P1 Meter*
2. Configure the serial port (I/O instance) for your P1 meter connection
3. Optionally enable "Track power generation" if you have solar panels

| Option | Description |
|--------|-------------|
| Track power generation | Enable to track electricity generation (for solar/feed-in) |

## Variables

The module automatically creates and logs the following variables:

| Variable | Type | Profile | Description |
|----------|------|---------|-------------|
| CurrentPowerConsumption | Float | ~Watt.14490 | Current power consumption in Watts |
| ConsumedElectricityHigh | Float | ~Electricity | Total consumed electricity on high tariff (kWh) |
| ConsumedElectricityLow | Float | ~Electricity | Total consumed electricity on low tariff (kWh) |
| ConsumedGas | Float | ~Gas | Total consumed gas (m³) |

When "Track power generation" is enabled:

| Variable | Type | Profile | Description |
|----------|------|---------|-------------|
| CurrentPowerGeneration | Float | ~Watt.14490 | Current power generation in Watts |
| GeneratedElectricityHigh | Float | ~Electricity | Total generated electricity on high tariff (kWh) |
| GeneratedElectricityLow | Float | ~Electricity | Total generated electricity on low tariff (kWh) |

## Supported OBIS Codes

The module parses the following OBIS codes from P1 telegrams:

| OBIS Code | Description |
|-----------|-------------|
| 0-0:96.14.0 | Current tariff indicator |
| 1-0:1.7.0 | Current power consumption (kW) |
| 1-0:2.7.0 | Current power generation (kW) |
| 1-0:1.8.1 | Consumed electricity low tariff (kWh) |
| 1-0:1.8.2 | Consumed electricity high tariff (kWh) |
| 1-0:2.8.1 | Generated electricity low tariff (kWh) |
| 1-0:2.8.2 | Generated electricity high tariff (kWh) |
| 0-1:24.2.1 | Gas consumption (m³) |

## Data Logging

All variables are automatically logged to IP-Symcon's Archive Control with appropriate aggregation:

- **Current values** (power consumption/generation): No aggregation (instantaneous values)
- **Counter values** (electricity/gas totals): Counter aggregation with zero-value filtering

## Troubleshooting

Enable debug mode in IP-Symcon to see detailed telegram parsing information. The module will log:
- Missing required fields in P1 telegrams
- Invalid or malformed telegram data

Common issues:
- **No data received**: Check serial port configuration and cable connection
- **Partial data**: Ensure baud rate matches your meter (typically 115200 for DSMR 5.0)
- **Missing fields**: Some meters don't report all OBIS codes; check your meter's documentation

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## Author

Franklin van Velthuizen
