import type { ComponentType } from 'react';
import type { SvgIconProps } from '@mui/material';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import GavelIcon from '@mui/icons-material/Gavel';
import DirectionsBoatIcon from '@mui/icons-material/DirectionsBoat';
import WarehouseIcon from '@mui/icons-material/Warehouse';
import PaymentsIcon from '@mui/icons-material/Payments';
import GroupsIcon from '@mui/icons-material/Groups';
import InventoryIcon from '@mui/icons-material/Inventory';
import PublicIcon from '@mui/icons-material/Public';
import SecurityIcon from '@mui/icons-material/Security';
import SpeedIcon from '@mui/icons-material/Speed';
import SupportAgentIcon from '@mui/icons-material/SupportAgent';
import InsightsIcon from '@mui/icons-material/Insights';

export const ICON_MAP: Record<string, ComponentType<SvgIconProps>> = {
  local_shipping: LocalShippingIcon,
  gavel: GavelIcon,
  directions_boat: DirectionsBoatIcon,
  warehouse: WarehouseIcon,
  payments: PaymentsIcon,
  groups: GroupsIcon,
  inventory: InventoryIcon,
  public: PublicIcon,
  security: SecurityIcon,
  speed: SpeedIcon,
  support_agent: SupportAgentIcon,
  insights: InsightsIcon,
};

export const ICON_OPTIONS = Object.keys(ICON_MAP);
