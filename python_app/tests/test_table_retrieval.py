import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from python_app.main import structure_financial_table_text


class FinancialTableStructuringTests(unittest.TestCase):
    def test_adds_clear_total_revenue_statement_from_extracted_table(self):
        extracted = """
        Tabel 1. Realisasi Pendapatan Pemerintah Kota Surabaya 2018-2019
        2018 2019
        PENDAPATAN DAERAH 8,175,204,579,587 9,083,086,853,714 11.11
        """

        structured = structure_financial_table_text(extracted)

        statement = (
            "Total pendapatan daerah tahun 2018: Rp 8,175,204,579,587; "
            "tahun 2019: Rp 9,083,086,853,714."
        )
        self.assertIn("PENDAPATAN DAERAH 8,175,204,579,587 9,083,086,853,714 11.11", structured)
        self.assertIn(
            "PENDAPATAN DAERAH 8,175,204,579,587 9,083,086,853,714 11.11\n" + statement,
            structured,
        )
        chunks = list(__import__("python_app.main", fromlist=["chunk_text"]).chunk_text(structured))
        self.assertTrue(any(statement in chunk and "PENDAPATAN DAERAH" in chunk for chunk in chunks))

    def test_does_not_modify_text_without_matching_revenue_total(self):
        text = "Tidak ada baris total pendapatan pada cuplikan ini."
        self.assertEqual(structure_financial_table_text(text), text)


if __name__ == "__main__":
    unittest.main()
