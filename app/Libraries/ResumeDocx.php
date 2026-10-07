<?php

namespace App\Libraries;

use RuntimeException;
use ZipArchive;

/**
 * Builds a resume .docx from public/assets/template/Resume Template.docx.
 *
 * The template holds two sections: page 1 is the A4 layout and page 2 is the
 * Long (8.5 x 13 in) layout. The photo frame, the horizontal rule and the page
 * setup are copied from the matching section so the output looks like the
 * template; the text paragraphs are rebuilt from the resume data.
 */
class ResumeDocx
{
    private const TEMPLATE = FCPATH . 'assets/template/Resume Template.docx';

    /** Photo frame size in EMU (square, same footprint as the template photo). */
    private const PHOTO_EMU = 1275430;

    private string $paper;

    /** @var array<string,mixed> */
    private array $data;

    private ?string $photoFile;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data, string $paper = 'a4', ?string $photoFile = null)
    {
        $this->data      = $data;
        $this->paper     = $paper === 'long' ? 'long' : 'a4';
        $this->photoFile = $photoFile && is_file($photoFile) ? $photoFile : null;
    }

    /**
     * Returns the generated .docx as a binary string.
     */
    public function render(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to generate DOCX files.');
        }
        if (! is_file(self::TEMPLATE)) {
            throw new RuntimeException('DOCX template not found: ' . self::TEMPLATE);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'resume');
        copy(self::TEMPLATE, $tmp);

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('Unable to open the DOCX template.');
        }

        $document = $zip->getFromName('word/document.xml');
        $photo    = $this->photoFile ? Photo::squareJpeg($this->photoFile) : null;

        $zip->addFromString('word/document.xml', $this->buildDocument($document, $photo !== null));

        // Swap the sample picture for the applicant's photo (or drop it).
        $zip->deleteName('word/media/image1.png');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $rels = str_replace('Target="media/image1.png"', 'Target="media/photo.jpeg"', $rels);
        $zip->addFromString('word/_rels/document.xml.rels', $rels);
        if ($photo !== null) {
            $zip->addFromString('word/media/photo.jpeg', $photo);
        } else {
            $zip->addFromString('word/media/photo.jpeg', Photo::blankJpeg());
        }

        $types = $zip->getFromName('[Content_Types].xml');
        if (! str_contains($types, 'Extension="jpeg"')) {
            $types = str_replace('<Default Extension="png"', '<Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="png"', $types);
            $zip->addFromString('[Content_Types].xml', $types);
        }

        $core = $zip->getFromName('docProps/core.xml');
        if ($core !== false) {
            $name = $this->x($this->str('full_name'));
            $core = preg_replace('#<dc:title>.*?</dc:title>|<dc:title/>#s', '<dc:title>Resume - ' . $name . '</dc:title>', $core);
            $core = preg_replace('#<dc:creator>.*?</dc:creator>#s', '<dc:creator>' . $name . '</dc:creator>', $core);
            $zip->addFromString('docProps/core.xml', $core);
        }

        $zip->close();
        $out = file_get_contents($tmp);
        @unlink($tmp);

        return $out;
    }

    private function buildDocument(string $xml, bool $hasPhoto): string
    {
        $bodyStart = strpos($xml, '<w:body>') + strlen('<w:body>');
        $bodyEnd   = strrpos($xml, '</w:body>');
        $body      = substr($xml, $bodyStart, $bodyEnd - $bodyStart);

        // Split the body at the first section break: [A4 section, Long section].
        preg_match('#<w:sectPr\b.*?</w:sectPr>#s', $body, $a4Sect);
        $breakPos = strpos($body, $a4Sect[0]);
        $breakPos = strpos($body, '</w:p>', $breakPos) + strlen('</w:p>');
        $segment  = $this->paper === 'a4' ? substr($body, 0, $breakPos) : substr($body, $breakPos);

        if ($this->paper === 'a4') {
            $sectPr = $a4Sect[0];
        } else {
            preg_match_all('#<w:sectPr\b.*?</w:sectPr>#s', $body, $all);
            $sectPr = end($all[0]);
        }

        // The photo run is the first run holding a picture; the rule is the run with the drawing group.
        preg_match('#<w:r\b[^>]*>(?:(?!</w:r>).)*?<w:drawing>(?:(?!</w:r>).)*?<pic:pic\b.*?</w:r>#s', $segment, $photoRun);
        preg_match('#<w:r>(?:(?!</w:r>).)*?<mc:AlternateContent>.*?</mc:AlternateContent></w:r>#s', $segment, $ruleRun);

        $photo = '';
        if ($hasPhoto && $photoRun) {
            $photo = str_replace('<w:lastRenderedPageBreak/>', '', $photoRun[0]);
            $photo = preg_replace('#<wp:extent cx="\d+" cy="\d+"/>#', '<wp:extent cx="' . self::PHOTO_EMU . '" cy="' . self::PHOTO_EMU . '"/>', $photo);
            $photo = preg_replace('#<a:ext cx="\d+" cy="\d+"/>#', '<a:ext cx="' . self::PHOTO_EMU . '" cy="' . self::PHOTO_EMU . '"/>', $photo, 1);
            $photo = preg_replace('#descr="[^"]*"#', 'descr="Applicant photo"', $photo);
        }

        return substr($xml, 0, $bodyStart) . $this->buildBody($photo, $ruleRun[0] ?? '') . $sectPr . substr($xml, $bodyEnd);
    }

    private function buildBody(string $photoRun, string $ruleRun): string
    {
        $d   = $this->data;
        $out = [];

        // Header: name (with the anchored photo), address and contact lines.
        $out[] = '<w:p><w:pPr><w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/></w:pPr>'
            . $photoRun . $this->run(mb_strtoupper($this->str('full_name')), ['b', 'sz' => 36]) . '</w:p>';

        $contact = $this->str('contact');
        $email   = $this->str('email');
        foreach ([
            $this->str('street'),
            $this->str('location'),
            $contact !== '' ? 'Contact No.: ' . $contact : '',
            $email !== '' ? 'Email: ' . $email : '',
        ] as $line) {
            // Keep the line count fixed: the rule below is anchored to the page.
            $out[] = $this->para($line === '' ? '' : $this->run($line), '<w:ind w:left="-5" w:right="43"/>');
        }

        $out[] = '<w:p><w:pPr><w:ind w:left="-5" w:right="43"/></w:pPr>' . $ruleRun . '</w:p>';

        // Objective (optional).
        $objective = $this->str('objective');
        if ($objective !== '') {
            $out[] = $this->title('OBJECTIVE', '<w:ind w:left="-5" w:right="43"/>');
            foreach (preg_split('/\R+/', $objective) as $line) {
                if (trim($line) !== '') {
                    $out[] = $this->para($this->run(trim($line)), '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/><w:jc w:val="both"/>');
                }
            }
            $out[] = $this->blank();
        }

        // Personal data.
        $out[] = $this->title('PERSONAL DATA', '<w:ind w:left="-5" w:right="43"/>');
        $dob   = $this->str('date_of_birth');
        $rows  = [
            'Date of Birth:' => $dob !== '' && strtotime($dob) ? date('F j, Y', strtotime($dob)) : '',
            'Citizenship:'   => $this->str('citizenship'),
            'Sex:'           => $this->str('sex'),
            'Civil Status:'  => $this->str('civil_status'),
            'Height:'        => $this->str('height'),
            'Weight:'        => $this->str('weight'),
        ];
        foreach ($rows as $label => $value) {
            if ($value !== '') {
                $out[] = $this->para($this->run($label, ['b']) . '<w:r><w:tab/></w:r>' . $this->run($value), '<w:tabs><w:tab w:val="left" w:pos="2160"/></w:tabs><w:ind w:left="0" w:firstLine="0"/>');
            }
        }
        if (($languages = $this->str('languages')) !== '') {
            $out[] = $this->para($this->run('Languages/Dialect Spoken: ', ['b']) . $this->run($languages), '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/>');
        }

        // Skills & qualifications (optional).
        $skills = $this->list('skills');
        if ($skills) {
            $out[] = $this->blank();
            $out[] = $this->title('SKILLS & QUALIFICATIONS', '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/>');
            foreach ($skills as $skill) {
                $out[] = $this->para($this->run($skill), '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/>');
            }
        }

        // Educational attainment.
        $education = $this->rows('education', ['level', 'school', 'address', 'years']);
        if ($education) {
            $out[] = $this->blank();
            $out[] = $this->title('EDUCATIONAL ATTAINMENT');
            foreach ($education as $e) {
                if ($e['level'] !== '') {
                    $out[] = $this->para($this->run($e['level'], ['b']));
                }
                foreach (['school', 'address', 'years'] as $k) {
                    if ($e[$k] !== '') {
                        $out[] = $this->para($this->run($e[$k]));
                    }
                }
            }
        }

        // Achievements (optional).
        $achievements = $this->list('achievements');
        if ($achievements) {
            $out[] = $this->blank();
            $out[] = $this->title('ACHIEVEMENT');
            foreach ($achievements as $a) {
                $out[] = $this->para($this->run($a, ['b']), '<w:pStyle w:val="ListParagraph1"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:ind w:hanging="540"/>');
            }
        }

        // Character references.
        $references = $this->rows('references', ['name', 'contact', 'position', 'organization']);
        if ($references) {
            $out[] = $this->blank();
            $out[] = $this->title('CHARACTER REFERENCES');
            foreach ($references as $r) {
                $runs = $this->run($r['name'], ['b']);
                if ($r['contact'] !== '') {
                    $runs .= '<w:r><w:tab/></w:r>' . $this->run($r['contact'], ['b']);
                }
                $out[] = $this->para($runs, '<w:pStyle w:val="ListParagraph1"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr><w:tabs><w:tab w:val="left" w:pos="6480"/></w:tabs><w:ind w:left="720" w:right="43"/>');
                foreach (['position', 'organization'] as $k) {
                    if ($r[$k] !== '') {
                        $out[] = $this->para($this->run($r[$k]), '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="705"/>');
                    }
                }
            }
        }

        // Signature block.
        $out[] = $this->para('', '<w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="705"/>');
        $out[] = $this->para('', '<w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="both"/>');
        $out[] = $this->para($this->run(mb_strtoupper($this->str('full_name'))), '<w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="both"/>');
        $out[] = $this->para($this->run('Applicant', ['i']), '<w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="both"/>');

        return implode('', $out);
    }

    private function title(string $text, string $pPr = ''): string
    {
        return $this->para($this->run($text, ['b', 'u']) . $this->run(':', ['b']), $pPr);
    }

    private function blank(): string
    {
        return $this->para('', '<w:spacing w:after="0" w:line="259" w:lineRule="auto"/><w:ind w:left="0" w:firstLine="0"/>');
    }

    private function para(string $runs, string $pPr = ''): string
    {
        return '<w:p>' . ($pPr !== '' ? '<w:pPr>' . $pPr . '</w:pPr>' : '') . $runs . '</w:p>';
    }

    /**
     * @param array<int|string,mixed> $fmt ['b', 'i', 'u', 'sz' => half-points]
     */
    private function run(string $text, array $fmt = []): string
    {
        $rPr = '';
        if (in_array('b', $fmt, true)) {
            $rPr .= '<w:b/><w:bCs/>';
        }
        if (in_array('i', $fmt, true)) {
            $rPr .= '<w:i/><w:iCs/>';
        }
        if (in_array('u', $fmt, true)) {
            $rPr .= '<w:u w:val="single"/>';
        }
        if (isset($fmt['sz'])) {
            $rPr .= '<w:sz w:val="' . (int) $fmt['sz'] . '"/><w:szCs w:val="' . (int) $fmt['sz'] . '"/>';
        }

        return '<w:r>' . ($rPr !== '' ? '<w:rPr>' . $rPr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . $this->x($text) . '</w:t></w:r>';
    }

    private function str(string $key): string
    {
        $v = $this->data[$key] ?? '';

        return is_string($v) ? trim($v) : '';
    }

    /**
     * @return list<string>
     */
    private function list(string $key): array
    {
        $items = is_array($this->data[$key] ?? null) ? $this->data[$key] : [];

        return array_values(array_filter(array_map(static fn ($v) => is_string($v) ? trim($v) : '', $items), static fn ($v) => $v !== ''));
    }

    /**
     * @param list<string> $fields
     *
     * @return list<array<string,string>>
     */
    private function rows(string $key, array $fields): array
    {
        $rows = [];
        foreach (is_array($this->data[$key] ?? null) ? $this->data[$key] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($fields as $f) {
                $clean[$f] = is_string($row[$f] ?? null) ? trim($row[$f]) : '';
            }
            if (implode('', $clean) !== '') {
                $rows[] = $clean;
            }
        }

        return $rows;
    }

    private function x(string $s): string
    {
        // Strip characters that are not allowed in XML 1.0.
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
