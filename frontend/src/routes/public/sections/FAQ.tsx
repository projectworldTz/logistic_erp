import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Box,
  Container,
  Stack,
  Typography,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import type { FaqsContent } from '../../../types';

interface FAQProps {
  content: FaqsContent;
}

export function FAQ({ content }: FAQProps) {
  return (
    <Box id="faq" sx={{ py: 10, bgcolor: 'background.paper' }}>
      <Container maxWidth="md">
        <Stack spacing={1} textAlign="center" sx={{ mb: 6 }}>
          <Typography variant="h4" fontWeight={700}>
            {content.heading}
          </Typography>
        </Stack>
        <Stack spacing={1}>
          {content.items.map((faq) => (
            <Accordion key={faq.question} variant="outlined" disableGutters>
              <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                <Typography fontWeight={600}>{faq.question}</Typography>
              </AccordionSummary>
              <AccordionDetails>
                <Typography color="text.secondary">{faq.answer}</Typography>
              </AccordionDetails>
            </Accordion>
          ))}
        </Stack>
      </Container>
    </Box>
  );
}
